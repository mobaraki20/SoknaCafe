<?php
declare(strict_types=1);

function xlsx_xml(string $value): string
{
    return htmlspecialchars($value, ENT_XML1 | ENT_QUOTES, 'UTF-8');
}

function xlsx_col_name(int $index): string
{
    $name = '';
    while ($index > 0) {
        $index--;
        $name = chr(65 + ($index % 26)) . $name;
        $index = intdiv($index, 26);
    }
    return $name;
}

function xlsx_cell(mixed $value, string $style = 'text', int $merge = 1): array
{
    return ['v' => $value, 's' => $style, 'merge' => max(1, $merge)];
}

function xlsx_styles_xml(): string
{
    return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
        .'<styleSheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">'
        .'<numFmts count="1"><numFmt numFmtId="164" formatCode="#\,##0 &quot;تومان&quot;"/></numFmts>'
        .'<fonts count="4">'
        .'<font><sz val="11"/><name val="Vazirmatn"/><family val="2"/></font>'
        .'<font><b/><sz val="16"/><color rgb="FFFFFFFF"/><name val="Vazirmatn"/></font>'
        .'<font><b/><sz val="11"/><color rgb="FF24342C"/><name val="Vazirmatn"/></font>'
        .'<font><sz val="10"/><color rgb="FF6E6A63"/><name val="Vazirmatn"/></font>'
        .'</fonts>'
        .'<fills count="5"><fill><patternFill patternType="none"/></fill><fill><patternFill patternType="gray125"/></fill>'
        .'<fill><patternFill patternType="solid"><fgColor rgb="FF365B4C"/><bgColor indexed="64"/></patternFill></fill>'
        .'<fill><patternFill patternType="solid"><fgColor rgb="FFF1ECE3"/><bgColor indexed="64"/></patternFill></fill>'
        .'<fill><patternFill patternType="solid"><fgColor rgb="FFFFF3D9"/><bgColor indexed="64"/></patternFill></fill></fills>'
        .'<borders count="2"><border/><border><left style="thin"><color rgb="FFE0D9CF"/></left><right style="thin"><color rgb="FFE0D9CF"/></right><top style="thin"><color rgb="FFE0D9CF"/></top><bottom style="thin"><color rgb="FFE0D9CF"/></bottom></border></borders>'
        .'<cellStyleXfs count="1"><xf numFmtId="0" fontId="0" fillId="0" borderId="0"/></cellStyleXfs>'
        .'<cellXfs count="10">'
        .'<xf numFmtId="0" fontId="0" fillId="0" borderId="0" xfId="0" applyAlignment="1"><alignment horizontal="right" vertical="center" readingOrder="2" wrapText="1"/></xf>'
        .'<xf numFmtId="0" fontId="1" fillId="2" borderId="0" xfId="0" applyAlignment="1"><alignment horizontal="right" vertical="center" readingOrder="2"/></xf>'
        .'<xf numFmtId="0" fontId="3" fillId="0" borderId="0" xfId="0" applyAlignment="1"><alignment horizontal="right" vertical="center" readingOrder="2" wrapText="1"/></xf>'
        .'<xf numFmtId="0" fontId="2" fillId="3" borderId="1" xfId="0" applyAlignment="1"><alignment horizontal="right" vertical="center" readingOrder="2" wrapText="1"/></xf>'
        .'<xf numFmtId="3" fontId="0" fillId="0" borderId="1" xfId="0" applyNumberFormat="1" applyAlignment="1"><alignment horizontal="right" vertical="center" readingOrder="2"/></xf>'
        .'<xf numFmtId="164" fontId="0" fillId="0" borderId="1" xfId="0" applyNumberFormat="1" applyAlignment="1"><alignment horizontal="right" vertical="center" readingOrder="2"/></xf>'
        .'<xf numFmtId="10" fontId="0" fillId="0" borderId="1" xfId="0" applyNumberFormat="1" applyAlignment="1"><alignment horizontal="right" vertical="center" readingOrder="2"/></xf>'
        .'<xf numFmtId="0" fontId="0" fillId="4" borderId="1" xfId="0" applyAlignment="1"><alignment horizontal="right" vertical="center" readingOrder="2" wrapText="1"/></xf>'
        .'<xf numFmtId="0" fontId="2" fillId="0" borderId="0" xfId="0" applyAlignment="1"><alignment horizontal="right" vertical="center" readingOrder="2"/></xf>'
        .'<xf numFmtId="0" fontId="0" fillId="0" borderId="1" xfId="0" applyAlignment="1"><alignment horizontal="right" vertical="top" readingOrder="2" wrapText="1"/></xf>'
        .'</cellXfs>'
        .'<cellStyles count="1"><cellStyle name="Normal" xfId="0" builtinId="0"/></cellStyles>'
        .'</styleSheet>';
}

function xlsx_style_index(string $style): int
{
    return match ($style) {
        'title' => 1,
        'meta' => 2,
        'header' => 3,
        'integer' => 4,
        'money' => 5,
        'percent' => 6,
        'warning' => 7,
        'section' => 8,
        'wrap' => 9,
        default => 0,
    };
}

function xlsx_sheet_xml(array $sheet): string
{
    $rows = $sheet['rows'] ?? [];
    $widths = $sheet['widths'] ?? [];
    $merges = [];
    $xmlRows = '';
    foreach ($rows as $rIndex => $row) {
        $excelRow = $rIndex + 1;
        $cellsXml = '';
        $col = 1;
        foreach ($row as $rawCell) {
            $cell = is_array($rawCell) && array_key_exists('v', $rawCell) ? $rawCell : xlsx_cell($rawCell);
            $value = $cell['v'];
            $style = xlsx_style_index((string)($cell['s'] ?? 'text'));
            $merge = max(1, (int)($cell['merge'] ?? 1));
            $ref = xlsx_col_name($col) . $excelRow;
            if ($merge > 1) {
                $merges[] = $ref . ':' . xlsx_col_name($col + $merge - 1) . $excelRow;
            }
            if (is_int($value) || is_float($value)) {
                $cellsXml .= '<c r="'.$ref.'" s="'.$style.'" t="n"><v>'.htmlspecialchars((string)$value, ENT_XML1).'</v></c>';
            } else {
                $text = (string)($value ?? '');
                $cellsXml .= '<c r="'.$ref.'" s="'.$style.'" t="inlineStr"><is><t xml:space="preserve">'.xlsx_xml($text).'</t></is></c>';
            }
            $col += $merge;
        }
        $height = ($rIndex === 0) ? ' ht="28" customHeight="1"' : '';
        $xmlRows .= '<row r="'.$excelRow.'"'.$height.'>'.$cellsXml.'</row>';
    }

    $colsXml = '';
    foreach ($widths as $i => $width) {
        $n = $i + 1;
        $colsXml .= '<col min="'.$n.'" max="'.$n.'" width="'.max(8, (float)$width).'" customWidth="1"/>';
    }
    $cols = $colsXml !== '' ? '<cols>'.$colsXml.'</cols>' : '';
    $mergeXml = $merges ? '<mergeCells count="'.count($merges).'">'.implode('', array_map(static fn($m)=>'<mergeCell ref="'.$m.'"/>', $merges)).'</mergeCells>' : '';
    $freeze = max(0, (int)($sheet['freeze_row'] ?? 0));
    $pane = $freeze > 0 ? '<pane ySplit="'.$freeze.'" topLeftCell="A'.($freeze+1).'" activePane="bottomLeft" state="frozen"/>' : '';
    $autoFilter = trim((string)($sheet['auto_filter'] ?? ''));
    $filterXml = $autoFilter !== '' ? '<autoFilter ref="'.xlsx_xml($autoFilter).'"/>' : '';
    $orientation = !empty($sheet['landscape']) ? ' orientation="landscape"' : '';

    return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
        .'<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">'
        .'<sheetViews><sheetView workbookViewId="0" rightToLeft="1">'.$pane.'</sheetView></sheetViews>'
        .$cols.'<sheetData>'.$xmlRows.'</sheetData>'.$filterXml.$mergeXml
        .'<pageMargins left="0.3" right="0.3" top="0.5" bottom="0.5" header="0.2" footer="0.2"/><pageSetup paperSize="9" fitToWidth="1" fitToHeight="0"'.$orientation.'/>'
        .'</worksheet>';
}

function xlsx_zip_store(array $files): string
{
    $body = '';
    $central = '';
    $offset = 0;
    $flags = 0x0800; // UTF-8 filenames.
    foreach ($files as $name => $content) {
        $name = (string)$name;
        $content = (string)$content;
        $nameBytes = $name;
        $crc = crc32($content);
        $size = strlen($content);
        $local = pack('VvvvvvVVVvv', 0x04034b50, 20, $flags, 0, 0, 0, $crc, $size, $size, strlen($nameBytes), 0)
            . $nameBytes . $content;
        $central .= pack('VvvvvvvVVVvvvvvVV', 0x02014b50, 20, 20, $flags, 0, 0, 0, $crc, $size, $size, strlen($nameBytes), 0, 0, 0, 0, 0, $offset)
            . $nameBytes;
        $body .= $local;
        $offset += strlen($local);
    }
    $count = count($files);
    return $body . $central . pack('VvvvvVVv', 0x06054b50, 0, 0, $count, $count, strlen($central), strlen($body), 0);
}

function xlsx_build(array $sheets): string
{
    if (!$sheets) throw new RuntimeException('داده‌ای برای خروجی Excel آماده نشده است.');

    $files = [];
    $sheetOverrides = '';
    $workbookSheets = '';
    $rels = '';
    foreach (array_values($sheets) as $i => $sheet) {
        $n = $i + 1;
        $name = trim((string)($sheet['name'] ?? ('گزارش '.$n))) ?: ('گزارش '.$n);
        $name = text_substr(str_replace(['\\','/','?','*','[',']',':'], ' ', $name), 0, 31);
        $sheetOverrides .= '<Override PartName="/xl/worksheets/sheet'.$n.'.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/>';
        $workbookSheets .= '<sheet name="'.xlsx_xml($name).'" sheetId="'.$n.'" r:id="rId'.$n.'"/>';
        $rels .= '<Relationship Id="rId'.$n.'" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet'.$n.'.xml"/>';
        $files['xl/worksheets/sheet'.$n.'.xml'] = xlsx_sheet_xml($sheet);
    }
    $styleRid = count($sheets) + 1;
    $rels .= '<Relationship Id="rId'.$styleRid.'" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles" Target="styles.xml"/>';

    $files['[Content_Types].xml'] = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types"><Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/><Default Extension="xml" ContentType="application/xml"/><Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/><Override PartName="/xl/styles.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.styles+xml"/>'.$sheetOverrides.'</Types>';
    $files['_rels/.rels'] = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/></Relationships>';
    $files['xl/workbook.xml'] = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships"><bookViews><workbookView/></bookViews><sheets>'.$workbookSheets.'</sheets></workbook>';
    $files['xl/_rels/workbook.xml.rels'] = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'.$rels.'</Relationships>';
    $files['xl/styles.xml'] = xlsx_styles_xml();

    // ZipArchive is optional: use it when available, otherwise write a standards-compliant
    // stored ZIP ourselves so report export does not depend on a hosting extension.
    if (class_exists('ZipArchive')) {
        $tmp = tempnam(sys_get_temp_dir(), 'sokna-xlsx-');
        if ($tmp === false) throw new RuntimeException('ساخت فایل موقت گزارش ممکن نشد.');
        $zip = new ZipArchive();
        if ($zip->open($tmp, ZipArchive::OVERWRITE) !== true) throw new RuntimeException('ساخت فایل Excel ممکن نشد.');
        foreach ($files as $path => $content) $zip->addFromString($path, $content);
        $zip->close();
        $bytes = file_get_contents($tmp);
        @unlink($tmp);
        if ($bytes === false) throw new RuntimeException('خواندن فایل Excel ساخته‌شده ممکن نشد.');
        return $bytes;
    }
    return xlsx_zip_store($files);
}

function xlsx_download(string $filename, array $sheets): never
{
    $bytes = xlsx_build($sheets);
    $safe = preg_replace('/[^a-zA-Z0-9._-]+/', '-', $filename) ?: 'sokna-report.xlsx';
    if (!str_ends_with(strtolower($safe), '.xlsx')) $safe .= '.xlsx';
    while (ob_get_level() > 0) @ob_end_clean();
    header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    header('Content-Disposition: attachment; filename="'.$safe.'"');
    header('Content-Length: '.strlen($bytes));
    header('X-Content-Type-Options: nosniff');
    header('Cache-Control: private, no-store, max-age=0');
    echo $bytes;
    exit;
}
