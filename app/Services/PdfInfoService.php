<?php

namespace App\Services;

use Exception;
use SplFileInfo;

// for using this service you should have installed "pdftoppm"
class PdfInfoService
{
    protected SplFileInfo $file;
    public array $output;

    public ?string $title;
    public ?string $author;
    public ?string $creator;
    public ?string $producer;
    public ?string $creationDate;
    public ?string $modDate;
    public ?string $tagged;
    public ?string $form;
    public int $pages;
    public ?string $encrypted;
    public ?string $pageSize;
    public ?string $fileSize;
    public ?string $optimized;
    public ?string $PDFVersion;
    public ?string $pageRot;

    public static string $bin;

  /**
   * @throws Exception
   */
    public function __construct(SplFileInfo $file)
    {
        $this->file = $file;

        $this->loadOutput();

        $this->parseOutput();
    }

    public function getBinary(): string
    {
        if (empty(static::$bin)) {
            static::$bin = 'pdfinfo';
        }

        return static::$bin;
    }

  /**
   * @throws Exception
   */
    private function loadOutput(): void
    {
        $cmd = escapeshellarg($this->getBinary()); // escapeshellarg to work with Windows paths with spaces.

        $file = escapeshellarg($this->file);
      // Parse entire output
      // Surround with double quotes if file name has spaces
        exec("$cmd $file", $output, $returnVar);

        if ($returnVar === 1) {
            throw new Exception('Can not open PDF for get number of pages');
        } elseif ($returnVar === 2) {
            throw new Exception('Can not open output PDF for get number of pages');
        } elseif ($returnVar === 3) {
            throw new Exception('PDF Permission Exception');
        } elseif ($returnVar === 99) {
            throw new Exception();
        } elseif ($returnVar === 127) {
            throw new Exception('command not found');
        }

        $this->output = $output;
    }

    private function parseOutput(): void
    {
        $this->title = $this->parse('Title');
        $this->author = $this->parse('Author');
        $this->creator = $this->parse('Creator');
        $this->producer = $this->parse('Producer');
        $this->creationDate = $this->parse('CreationDate');
        $this->modDate = $this->parse('ModDate');
        $this->tagged = $this->parse('Tagged');
        $this->form = $this->parse('Form');
        $this->pages = (int)$this->parse('Pages');
        $this->encrypted = $this->parse('Encrypted');
        $this->pageSize = $this->parse('Page size');
        $this->fileSize = $this->parse('File size');
        $this->optimized = $this->parse('Optimized');
        $this->PDFVersion = $this->parse('PDF version');
        $this->pageRot = $this->parse('Page rot');
    }

    private function parse(string $attribute): ?string
    {
      // Iterate through lines
        $result = null;
        foreach ($this->output as $op) {
          // Extract the number
            if (preg_match("/" . $attribute . ":\s*(.+)/i", $op, $matches) === 1) {
                $result = $matches[1];
                break;
            }
        }

        return $result;
    }
}
