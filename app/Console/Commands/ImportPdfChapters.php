<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use App\Models\Series;
use App\Models\Chapter;
use App\Jobs\ProcessChapterPdf;

class ImportPdfChapters extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'import:pdf-chapters 
                            {--paid : Mark the imported chapters as paid instead of free}
                            {--price=10 : Default diamond price for paid chapters}
                            {--sync : Process PDF page-slicing synchronously in-process instead of queuing}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Import chapters from PDF files placed in storage/app/public/chapters/ based on naming conventions';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info("Scanning storage/app/public/chapters/ for PDF files...");

        // Get all files in storage/app/public/chapters
        $files = Storage::disk('public')->files('chapters');
        
        $pdfFiles = array_filter($files, function($file) {
            return strtolower(pathinfo($file, PATHINFO_EXTENSION)) === 'pdf' 
                && !preg_match('/^chapters\/chapter_\d+_\d+\.pdf$/i', $file); // Skip already processed system files
        });

        if (empty($pdfFiles)) {
            $this->warn("No new PDF files found to import in storage/app/public/chapters/.");
            return 0;
        }

        $this->info("Found " . count($pdfFiles) . " PDF file(s) to process.");

        $paid = $this->option('paid');
        $price = (int)$this->option('price');
        $sync = $this->option('sync');

        foreach ($pdfFiles as $filePath) {
            $filename = basename($filePath);
            $this->comment("--------------------------------------------------");
            $this->info("Processing file: {$filename}");

            $parsed = $this->parseFilename($filename);
            if (!$parsed) {
                $this->error("Could not parse series slug and chapter number from filename: {$filename}");
                continue;
            }

            $seriesSlug = $parsed['series_slug'];
            $chapterNumber = $parsed['chapter_number'];
            $rawSeriesName = $parsed['series_name_raw'];

            $this->line("Parsed Series Slug: <info>{$seriesSlug}</info>, Chapter: <info>{$chapterNumber}</info>");

            // Find the series in the database
            $series = Series::where('slug', $seriesSlug)
                ->orWhere('title', 'like', '%' . $rawSeriesName . '%')
                ->first();

            if (!$series) {
                // Try fuzzy slug match
                $series = Series::all()->first(function($s) use ($seriesSlug) {
                    return str_contains($s->slug, $seriesSlug) || str_contains($seriesSlug, $s->slug);
                });
            }

            if (!$series) {
                $this->error("Series not found for slug '{$seriesSlug}' or name '{$rawSeriesName}'. Skipping.");
                continue;
            }

            $this->info("Found matching Series: ID {$series->id} - \"{$series->title}\"");

            // Check if chapter already exists
            $exists = Chapter::where('series_id', $series->id)
                ->where('chapter_number', $chapterNumber)
                ->exists();

            if ($exists) {
                $this->warn("Chapter {$chapterNumber} already exists for \"{$series->title}\". Skipping.");
                continue;
            }

            // Create database record
            $chapter = Chapter::create([
                'series_id' => $series->id,
                'chapter_number' => $chapterNumber,
                'title' => "Bob " . $chapterNumber,
                'is_free' => !$paid,
                'price_in_diamonds' => $paid ? $price : 0,
                'published_at' => now(),
            ]);

            // Rename PDF to system layout: chapters/chapter_{id}_{time}.pdf
            $newPath = 'chapters/chapter_' . $chapter->id . '_' . time() . '.pdf';
            
            // Move PDF file
            if (Storage::disk('public')->move($filePath, $newPath)) {
                $chapter->pdf_path = $newPath;
                $chapter->save();
                $this->info("PDF renamed and moved to storage/public/{$newPath}");

                // Slicing PDF into WebP images
                if ($sync) {
                    $this->info("Processing PDF into WebP images (synchronously)...");
                    ProcessChapterPdf::dispatchSync($chapter);
                    $this->info("Slicing complete!");
                } else {
                    $this->info("Dispatching PDF processing job to the queue...");
                    ProcessChapterPdf::dispatch($chapter);
                }
            } else {
                $chapter->delete();
                $this->error("Failed to move PDF file: {$filePath}");
            }
        }

        $this->info("--------------------------------------------------");
        $this->info("All done!");
        return 0;
    }

    /**
     * Parse filename to extract series slug and chapter number.
     */
    private function parseFilename($filename)
    {
        // Remove extension (.pdf)
        $nameWithoutExt = pathinfo($filename, PATHINFO_FILENAME);

        // Remove suffix extensions/phrases case-insensitively (e.g. compressed, Сжато, сжато)
        $cleanName = preg_replace('/[_\-\s]*(?:compressed|Сжато|сжато)$/ui', '', $nameWithoutExt);

        // Remove duplicate indicators like (1), (2)
        $cleanName = preg_replace('/\(\d+\)$/', '', trim($cleanName));

        // Trim trailing space, dash or underscore characters
        $cleanName = trim($cleanName, " \t\n\r\0\x0B—–-_");

        // Try standard format: {series_slug} {chapter_number} or {series_slug}_{chapter_number}
        // Also supports suffix like "-bob" or "-qism"
        // Handles separators like space, underscore, or em-dash/en-dash/hyphen
        if (preg_match('/(?:[\s_—–-]+)(\d+(?:\.\d+)?)(?:-bob|-qism)?$/iu', $cleanName, $matches)) {
            $chapterNumber = floatval($matches[1]);
            $matchedPart = $matches[0];
            $seriesPart = substr($cleanName, 0, strrpos($cleanName, $matchedPart));
        } else {
            // Fallback: search for any number in the string
            if (preg_match('/(\d+(?:\.\d+)?)/', $cleanName, $matches)) {
                $chapterNumber = floatval($matches[1]);
                $seriesPart = trim(str_replace($matches[0], '', $cleanName), " \t\n\r\0\x0B—–-_");
            } else {
                return null;
            }
        }

        // Clean series name (replace em-dashes, en-dashes, underscores with spaces)
        $seriesPart = str_replace(['—', '–', '_'], ' ', $seriesPart);
        $seriesSlug = Str::slug(trim($seriesPart));

        return [
            'series_slug' => $seriesSlug,
            'chapter_number' => $chapterNumber,
            'series_name_raw' => trim($seriesPart)
        ];
    }
}
