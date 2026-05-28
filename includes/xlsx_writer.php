<?php
/**
 * xlsx_writer.php — простой генератор XLSX-файлов без сторонних библиотек
 *
 * XLSX = ZIP-архив с XML-файлами внутри.
 * Использует только встроенные расширения PHP: ZipArchive, DOM.
 *
 * Использование:
 *   $writer = new XlsxWriter();
 *   $writer->setHeaders(['ID', 'Имя', 'Сумма']);
 *   $writer->addRow([1, 'Иван', 1500]);
 *   $writer->addRow([2, 'Мария', 2300]);
 *   $writer->download('export.xlsx');
 */

class XlsxWriter
{
    private array $headers   = [];
    private array $rows      = [];
    private array $colWidths = [];   // индекс колонки => ширина
    private array $rowStyles = [];   // номер строки => стиль (status_new, status_completed и т.д.)

    public function setHeaders(array $headers): void
    {
        $this->headers = $headers;
        // Авто-ширина по заголовкам
        foreach ($headers as $i => $h) {
            $this->colWidths[$i] = max(12, mb_strlen($h) + 2);
        }
    }

    /**
     * Добавить строку данных.
     * @param array  $values   значения колонок
     * @param string $rowStyle опционально: 'status_new', 'status_confirmed',
     *                                       'status_completed', 'status_cancelled'
     */
    public function addRow(array $values, string $rowStyle = ''): void
    {
        $rowIndex = count($this->rows);
        $this->rows[] = $values;
        if ($rowStyle !== '') {
            $this->rowStyles[$rowIndex] = $rowStyle;
        }

        // Авто-ширина колонок
        foreach ($values as $i => $v) {
            $len = mb_strlen((string)$v) + 2;
            if (!isset($this->colWidths[$i]) || $len > $this->colWidths[$i]) {
                $this->colWidths[$i] = min($len, 50); // максимум 50
            }
        }
    }

    /**
     * Сгенерировать XLSX и отправить как download.
     */
    public function download(string $filename = 'export.xlsx'): void
    {
        $tempFile = tempnam(sys_get_temp_dir(), 'xlsx_') . '.xlsx';
        $this->save($tempFile);

        // Заголовки для скачивания
        header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Content-Length: ' . filesize($tempFile));
        header('Cache-Control: no-cache, must-revalidate');
        header('Pragma: no-cache');

        readfile($tempFile);
        @unlink($tempFile);
        exit;
    }

    /**
     * Сохранить XLSX в указанный файл.
     */
    public function save(string $path): void
    {
        $zip = new ZipArchive();
        if ($zip->open($path, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            throw new \RuntimeException('Не удалось создать XLSX-файл');
        }

        // Структура XLSX (минимально необходимая)
        $zip->addFromString('[Content_Types].xml',     $this->contentTypesXml());
        $zip->addFromString('_rels/.rels',             $this->relsXml());
        $zip->addFromString('xl/workbook.xml',         $this->workbookXml());
        $zip->addFromString('xl/_rels/workbook.xml.rels', $this->workbookRelsXml());
        $zip->addFromString('xl/styles.xml',           $this->stylesXml());
        $zip->addFromString('xl/worksheets/sheet1.xml', $this->sheetXml());

        $zip->close();
    }

    // ─────────── XML генераторы ───────────

    private function contentTypesXml(): string
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">
  <Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>
  <Default Extension="xml" ContentType="application/xml"/>
  <Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/>
  <Override PartName="/xl/worksheets/sheet1.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/>
  <Override PartName="/xl/styles.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.styles+xml"/>
</Types>';
    }

    private function relsXml(): string
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">
  <Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/>
</Relationships>';
    }

    private function workbookXml(): string
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"
          xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">
  <sheets>
    <sheet name="Данные" sheetId="1" r:id="rId1"/>
  </sheets>
</workbook>';
    }

    private function workbookRelsXml(): string
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">
  <Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet1.xml"/>
  <Relationship Id="rId2" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles" Target="styles.xml"/>
</Relationships>';
    }

    /**
     * Стили: 0=обычный, 1=заголовок (зелёный фон, белый жирный),
     * 2=новая (жёлтый), 3=подтверждена (зелёный), 4=отменена (красный), 5=завершена (синий)
     */
    private function stylesXml(): string
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<styleSheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">
  <fonts count="6">
    <font><sz val="11"/><name val="Calibri"/></font>
    <font><b/><sz val="11"/><color rgb="FFFFFFFF"/><name val="Calibri"/></font>
    <font><sz val="11"/><color rgb="FF8C5400"/><name val="Calibri"/></font>
    <font><sz val="11"/><color rgb="FF1A6033"/><name val="Calibri"/></font>
    <font><sz val="11"/><color rgb="FFA01010"/><name val="Calibri"/></font>
    <font><sz val="11"/><color rgb="FF1A4977"/><name val="Calibri"/></font>
  </fonts>
  <fills count="6">
    <fill><patternFill patternType="none"/></fill>
    <fill><patternFill patternType="gray125"/></fill>
    <fill><patternFill patternType="solid"><fgColor rgb="FF0D4F3C"/></patternFill></fill>
    <fill><patternFill patternType="solid"><fgColor rgb="FFFFF3CD"/></patternFill></fill>
    <fill><patternFill patternType="solid"><fgColor rgb="FFD4F1DC"/></patternFill></fill>
    <fill><patternFill patternType="solid"><fgColor rgb="FFFFD4D4"/></patternFill></fill>
  </fills>
  <borders count="2">
    <border><left/><right/><top/><bottom/><diagonal/></border>
    <border>
      <left   style="thin"><color rgb="FFD8E6E0"/></left>
      <right  style="thin"><color rgb="FFD8E6E0"/></right>
      <top    style="thin"><color rgb="FFD8E6E0"/></top>
      <bottom style="thin"><color rgb="FFD8E6E0"/></bottom>
    </border>
  </borders>
  <cellXfs count="6">
    <xf numFmtId="0" fontId="0" fillId="0" borderId="0"/>
    <xf numFmtId="0" fontId="1" fillId="2" borderId="1" applyFont="1" applyFill="1" applyBorder="1" applyAlignment="1">
      <alignment horizontal="center" vertical="center"/>
    </xf>
    <xf numFmtId="0" fontId="2" fillId="3" borderId="1" applyFont="1" applyFill="1" applyBorder="1"/>
    <xf numFmtId="0" fontId="3" fillId="4" borderId="1" applyFont="1" applyFill="1" applyBorder="1"/>
    <xf numFmtId="0" fontId="4" fillId="5" borderId="1" applyFont="1" applyFill="1" applyBorder="1"/>
    <xf numFmtId="0" fontId="5" fillId="0" borderId="1" applyFont="1" applyBorder="1"/>
  </cellXfs>
</styleSheet>';
    }

    private function sheetXml(): string
    {
        $colsXml = '';
        foreach ($this->colWidths as $i => $w) {
            $col = $i + 1;
            $colsXml .= '<col min="' . $col . '" max="' . $col . '" width="' . $w . '" customWidth="1"/>';
        }

        // Заголовок (1-я строка)
        $rowsXml = '<row r="1" ht="22" customHeight="1">';
        foreach ($this->headers as $i => $h) {
            $cell = $this->cellRef($i, 1);
            $rowsXml .= '<c r="' . $cell . '" t="inlineStr" s="1"><is><t>' . $this->xmlEscape($h) . '</t></is></c>';
        }
        $rowsXml .= '</row>';

        // Данные
        foreach ($this->rows as $rowIdx => $row) {
            $rowNum   = $rowIdx + 2;
            $rowStyle = $this->rowStyles[$rowIdx] ?? '';
            $rowsXml .= '<row r="' . $rowNum . '">';
            foreach ($row as $colIdx => $val) {
                $cell  = $this->cellRef($colIdx, $rowNum);
                $style = $this->styleForRow($rowStyle);

                if (is_numeric($val) && !is_string($val)) {
                    $rowsXml .= '<c r="' . $cell . '" s="' . $style . '"><v>' . $val . '</v></c>';
                } else {
                    $rowsXml .= '<c r="' . $cell . '" t="inlineStr" s="' . $style . '"><is><t>'
                              . $this->xmlEscape((string)$val) . '</t></is></c>';
                }
            }
            $rowsXml .= '</row>';
        }

        $totalCols = count($this->headers);
        $lastCol   = $this->colLetter($totalCols - 1);
        $totalRows = count($this->rows) + 1;

        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">
  <sheetViews>
    <sheetView tabSelected="1" workbookViewId="0">
      <pane ySplit="1" topLeftCell="A2" activePane="bottomLeft" state="frozen"/>
    </sheetView>
  </sheetViews>
  <cols>' . $colsXml . '</cols>
  <sheetData>' . $rowsXml . '</sheetData>
  <autoFilter ref="A1:' . $lastCol . $totalRows . '"/>
</worksheet>';
    }

    private function styleForRow(string $rowStyle): int
    {
        return match ($rowStyle) {
            'status_new'       => 2,
            'status_confirmed' => 3,
            'status_cancelled' => 4,
            'status_completed' => 5,
            default            => 0,
        };
    }

    private function cellRef(int $colIdx, int $row): string
    {
        return $this->colLetter($colIdx) . $row;
    }

    private function colLetter(int $colIdx): string
    {
        $letter = '';
        $n = $colIdx;
        while ($n >= 0) {
            $letter = chr(65 + ($n % 26)) . $letter;
            $n = intdiv($n, 26) - 1;
        }
        return $letter;
    }

    private function xmlEscape(string $s): string
    {
        return htmlspecialchars($s, ENT_XML1 | ENT_QUOTES, 'UTF-8');
    }
}
