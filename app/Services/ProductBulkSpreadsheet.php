<?php

namespace App\Services;

use App\Models\Product;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use RuntimeException;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use ZipArchive;

class ProductBulkSpreadsheet
{
    public function download(): BinaryFileResponse
    {
        $path = tempnam(sys_get_temp_dir(), 'iron_products_');
        $products = Product::query()->whereNotNull('contabilium_id')->orderBy('code')->get();
        $rows = [['Codigo', 'Nombre', 'Unidades x Caja Fraccionados', 'Unidades x Caja Graneles']];

        foreach ($products as $product) {
            $rows[] = [
                $product->code,
                $product->description,
                $product->units_fractioned ?: null,
                $product->units_bulk ?: null,
            ];
        }

        $this->createXlsx($path, $rows);

        return response()->download($path, 'plantilla-unidades-por-caja.xlsx')->deleteFileAfterSend();
    }

    public function import(UploadedFile $file): array
    {
        $rows = $this->readXlsx($file->getRealPath());
        $header = array_map(fn ($value) => $this->normalize((string) $value), array_shift($rows) ?? []);

        $columns = array_flip($header);
        $sourceFormat = isset($columns['CANTIDAD POR CAJA'], $columns['FRACCION 1'], $columns['FRACCION X 100']);
        $fraction1Column = $columns['FRACCION 1'] ?? $columns['UNIDADES X CAJA FRACCIONADOS'] ?? null;
        $fractionX100Column = $columns['FRACCION X 100'] ?? null;
        $bulkColumn = $columns['CANTIDAD POR CAJA'] ?? $columns['UNIDADES X CAJA GRANELES'] ?? null;

        if (($columns['CODIGO'] ?? null) === null || $fraction1Column === null || $bulkColumn === null) {
            throw new RuntimeException('Usá la plantilla con las columnas Codigo, Unidades x Caja Fraccionados y Unidades x Caja Graneles.');
        }

        $products = Product::query()->whereNotNull('contabilium_id')->get()->keyBy(fn (Product $product) => $this->normalize($product->code));
        $updates = [];
        $newQuantities = [];
        $changedQuantities = [];
        $unchanged = 0;
        $processed = 0;
        $seen = [];
        $invalid = [];
        $unknown = [];

        foreach ($rows as $index => $row) {
            $code = trim((string) ($row[$columns['CODIGO']] ?? ''));
            $fraction1 = $row[$fraction1Column] ?? null;
            $fractionX100 = $fractionX100Column === null ? null : ($row[$fractionX100Column] ?? null);
            $fractioned = !$this->blank($fractionX100) ? $fractionX100 : $fraction1;
            $bulk = $row[$bulkColumn] ?? null;

            if ($code === '' && $this->blank($fractioned) && $this->blank($bulk)) {
                continue;
            }

            $key = $this->normalize($code);
            if ($key === '' || isset($seen[$key])) {
                $invalid[] = $index + 2;
                continue;
            }
            $seen[$key] = true;

            if (!isset($products[$key])) {
                $unknown[] = $code;
                continue;
            }

            if (!$sourceFormat && $this->blank($fractioned) && $this->blank($bulk)) {
                continue;
            }

            if (!$this->validQuantity($fractioned, $sourceFormat) || !$this->validQuantity($bulk, $sourceFormat)) {
                $invalid[] = $index + 2;
                continue;
            }

            $product = $products[$key];
            $incoming = [
                'units_fractioned' => $this->blank($fractioned) ? ($sourceFormat ? 0 : null) : (int) $fractioned,
                'units_fractioned_x100' => 0,
                'units_bulk' => $this->blank($bulk) ? ($sourceFormat ? 0 : null) : (int) $bulk,
            ];
            $values = [];
            $processed++;

            foreach ($incoming as $field => $quantity) {
                if ($quantity === null) continue;
                $current = (int) $product->{$field};
                if ($current === $quantity) continue;

                $values[$field] = $quantity;
                if ($field === 'units_fractioned_x100') continue;
                $item = [
                    'code' => $product->code,
                    'type' => $field === 'units_fractioned' ? 'Fraccionado' : 'Granel',
                    'from' => $current,
                    'to' => $quantity,
                ];
                if ($current === 0) $newQuantities[] = $item;
                else $changedQuantities[] = $item;
            }

            if ($values) $updates[] = [$product, $values];
            else $unchanged++;
        }

        if ($invalid) {
            $messages = [];
            if ($invalid) $messages[] = 'Filas inválidas o duplicadas: '.implode(', ', array_slice($invalid, 0, 20));
            throw new RuntimeException(implode(' | ', $messages));
        }

        if (!$processed) {
            throw new RuntimeException('El archivo no contiene cantidades para revisar.');
        }

        DB::transaction(function () use ($updates): void {
            foreach ($updates as [$product, $values]) $product->update($values);
        });

        return [
            'products_updated' => count($updates),
            'new' => $newQuantities,
            'changed' => $changedQuantities,
            'unchanged' => $unchanged,
            'unknown' => $unknown,
        ];
    }

    private function normalize(string $value): string
    {
        return mb_strtoupper(trim(Str::ascii($value)));
    }

    private function blank(mixed $value): bool
    {
        return $value === null || trim((string) $value) === '';
    }

    private function validQuantity(mixed $value, bool $allowZero = false): bool
    {
        return $this->blank($value) || (is_numeric($value) && (int) $value == $value && (int) $value >= ($allowZero ? 0 : 1));
    }

    private function readXlsx(string $path): array
    {
        $zip = new ZipArchive;
        if ($zip->open($path) !== true) throw new RuntimeException('No se pudo abrir el archivo Excel.');

        $shared = [];
        if (($xml = $zip->getFromName('xl/sharedStrings.xml')) !== false) {
            $document = simplexml_load_string($xml);
            foreach ($document->si ?? [] as $item) {
                $text = '';
                if (isset($item->t)) $text = (string) $item->t;
                else foreach ($item->r ?? [] as $run) $text .= (string) $run->t;
                $shared[] = $text;
            }
        }

        $sheetXml = $zip->getFromName('xl/worksheets/sheet1.xml');
        $zip->close();
        if ($sheetXml === false) throw new RuntimeException('El Excel no contiene una hoja válida.');

        $sheet = simplexml_load_string($sheetXml);
        $rows = [];
        foreach ($sheet->sheetData->row ?? [] as $row) {
            $values = ['', '', '', '', '', ''];
            foreach ($row->c ?? [] as $cell) {
                $reference = (string) $cell['r'];
                preg_match('/^[A-Z]+/', $reference, $match);
                $column = $this->columnIndex($match[0] ?? 'A');
                if ($column > 5) continue;
                $type = (string) $cell['t'];
                if ($type === 's') $value = $shared[(int) $cell->v] ?? '';
                elseif ($type === 'inlineStr') $value = (string) $cell->is->t;
                else $value = isset($cell->v) ? (string) $cell->v : '';
                $values[$column] = $value;
            }
            $rows[] = $values;
        }
        return $rows;
    }

    private function columnIndex(string $letters): int
    {
        $index = 0;
        foreach (str_split($letters) as $letter) $index = $index * 26 + ord($letter) - 64;
        return $index - 1;
    }

    private function createXlsx(string $path, array $rows): void
    {
        $sheetRows = '';
        foreach ($rows as $rowIndex => $row) {
            $number = $rowIndex + 1;
            $cells = '';
            foreach ($row as $columnIndex => $value) {
                $reference = chr(65 + $columnIndex).$number;
                if (is_numeric($value) && $columnIndex >= 2) {
                    $cells .= "<c r=\"{$reference}\" s=\"2\"><v>".(int) $value.'</v></c>';
                } else {
                    $escaped = htmlspecialchars((string) $value, ENT_XML1 | ENT_QUOTES, 'UTF-8');
                    $style = $rowIndex === 0 ? 1 : 0;
                    $cells .= "<c r=\"{$reference}\" s=\"{$style}\" t=\"inlineStr\"><is><t>{$escaped}</t></is></c>";
                }
            }
            $sheetRows .= "<row r=\"{$number}\">{$cells}</row>";
        }

        $files = [
            '[Content_Types].xml' => '<?xml version="1.0" encoding="UTF-8"?><Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types"><Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/><Default Extension="xml" ContentType="application/xml"/><Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/><Override PartName="/xl/worksheets/sheet1.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/><Override PartName="/xl/styles.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.styles+xml"/></Types>',
            '_rels/.rels' => '<?xml version="1.0" encoding="UTF-8"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/></Relationships>',
            'xl/workbook.xml' => '<?xml version="1.0" encoding="UTF-8"?><workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships"><sheets><sheet name="LISTA" sheetId="1" r:id="rId1"/></sheets></workbook>',
            'xl/_rels/workbook.xml.rels' => '<?xml version="1.0" encoding="UTF-8"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet1.xml"/><Relationship Id="rId2" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles" Target="styles.xml"/></Relationships>',
            'xl/styles.xml' => '<?xml version="1.0" encoding="UTF-8"?><styleSheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"><fonts count="2"><font><sz val="11"/><name val="Calibri"/></font><font><b/><color rgb="FFFFFFFF"/><sz val="11"/><name val="Calibri"/></font></fonts><fills count="3"><fill><patternFill patternType="none"/></fill><fill><patternFill patternType="gray125"/></fill><fill><patternFill patternType="solid"><fgColor rgb="FF4F81BD"/><bgColor indexed="64"/></patternFill></fill></fills><borders count="1"><border/></borders><cellStyleXfs count="1"><xf/></cellStyleXfs><cellXfs count="3"><xf fontId="0" fillId="0" borderId="0" xfId="0"/><xf fontId="1" fillId="2" borderId="0" xfId="0" applyFont="1" applyFill="1"/><xf fontId="0" fillId="0" borderId="0" xfId="0" numFmtId="3" applyNumberFormat="1"/></cellXfs></styleSheet>',
            'xl/worksheets/sheet1.xml' => '<?xml version="1.0" encoding="UTF-8"?><worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"><sheetViews><sheetView workbookViewId="0"><pane ySplit="1" topLeftCell="A2" activePane="bottomLeft" state="frozen"/></sheetView></sheetViews><cols><col min="1" max="1" width="24" customWidth="1"/><col min="2" max="2" width="60" customWidth="1"/><col min="3" max="4" width="30" customWidth="1"/></cols><sheetData>'.$sheetRows.'</sheetData><autoFilter ref="A1:D'.count($rows).'"/></worksheet>',
        ];

        $zip = new ZipArchive;
        if ($zip->open($path, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            throw new RuntimeException('No se pudo crear la plantilla Excel.');
        }
        foreach ($files as $name => $content) $zip->addFromString($name, $content);
        $zip->close();
    }
}
