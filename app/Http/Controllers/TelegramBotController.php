<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class TelegramBotController extends Controller
{
    /**
     * Handle incoming webhook requests from Telegram.
     */
    public function webhook(Request $request)
    {
        $update = $request->all();
        
        Log::info('Telegram Webhook Update received:', $update);

        $botToken = env('TELEGRAM_BOT_TOKEN');
        if (!$botToken) {
            Log::error('TELEGRAM_BOT_TOKEN is not configured in .env');
            return response()->json(['status' => 'error', 'message' => 'Bot token missing'], 500);
        }

        $frontendUrl = env('TELEGRAM_MINI_APP_URL', 'https://ecliptoon.uz');

        // 1. Handle Inline Query (e.g. typing @ecliptoon_bot "name")
        if (isset($update['inline_query'])) {
            return $this->handleInlineQuery($update['inline_query'], $botToken, $frontendUrl);
        }

        // 2. Handle Standard Message
        if (isset($update['message'])) {
            return $this->handleMessage($update['message'], $botToken, $frontendUrl);
        }

        return response()->json(['status' => 'ignored']);
    }

    /**
     * Handle Telegram inline query requests.
     */
    private function handleInlineQuery(array $inlineQuery, string $botToken, string $frontendUrl)
    {
        $queryId = $inlineQuery['id'];
        $queryText = trim($inlineQuery['query'] ?? '');

        if (empty($queryText)) {
            // Show latest 10 series as suggestions
            $seriesList = \App\Models\Series::orderBy('id', 'desc')->limit(10)->get();
        } else {
            // Search series by title, description, or alternative titles
            $seriesList = \App\Models\Series::where('title', 'like', "%{$queryText}%")
                ->orWhere('description', 'like', "%{$queryText}%")
                ->orWhere('alternative_titles', 'like', "%{$queryText}%")
                ->limit(10)
                ->get();
        }

        $results = [];
        foreach ($seriesList as $series) {
            $coverUrl = filter_var($series->cover_image, FILTER_VALIDATE_URL) 
                ? $series->cover_image 
                : asset('storage/' . $series->cover_image);

            $desc = strip_tags($series->description ?? '');
            $desc = strlen($desc) > 100 ? mb_substr($desc, 0, 97) . '...' : $desc;

            $alternativeText = "";
            if (!empty($series->alternative_titles)) {
                $altTitles = is_string($series->alternative_titles) 
                    ? json_decode($series->alternative_titles, true) 
                    : $series->alternative_titles;
                if (is_array($altTitles) && !empty($altTitles)) {
                    $alternativeText = "\nMuqobil: _" . implode(', ', $altTitles) . "_";
                }
            }

            $botUsername = env('TELEGRAM_BOT_USERNAME', 'ecliptoon_bot');
            $appShortName = env('TELEGRAM_APP_SHORT_NAME');

            $targetUrl = !empty($appShortName)
                ? "https://t.me/{$botUsername}/{$appShortName}?startapp=series_{$series->slug}"
                : "https://t.me/{$botUsername}?startapp=series_{$series->slug}";

            $messageText = "📚 *{$series->title}*{$alternativeText}\n\n" 
                . ($series->description ? strip_tags($series->description) . "\n\n" : "") 
                . "👉 [Manhvani o'qish]({$targetUrl})";

            $results[] = [
                'type' => 'article',
                'id' => 'series_' . $series->id,
                'title' => $series->title,
                'input_message_content' => [
                    'message_text' => $messageText,
                    'parse_mode' => 'Markdown',
                    'disable_web_page_preview' => false
                ],
                'reply_markup' => [
                    'inline_keyboard' => [
                        [
                            [
                                'text' => "📖 O'qish",
                                'url' => $targetUrl
                            ]
                        ]
                    ]
                ],
                'description' => $desc,
                'thumbnail_url' => $coverUrl,
                'thumb_url' => $coverUrl
            ];
        }

        $payload = [
            'inline_query_id' => $queryId,
            'results' => $results,
            'cache_time' => 300
        ];

        $telegramApiUrl = "https://api.telegram.org/bot{$botToken}/answerInlineQuery";
        $response = Http::post($telegramApiUrl, $payload);

        if ($response->failed()) {
            Log::error('Failed to answer Telegram inline query:', [
                'status' => $response->status(),
                'body' => $response->body()
            ]);
            return response()->json(['status' => 'failed', 'error' => $response->body()], 500);
        }

        return response()->json(['status' => 'success']);
    }

    /**
     * Handle regular bot chat messages.
     */
    private function handleMessage(array $message, string $botToken, string $frontendUrl)
    {
        $chatId = $message['chat']['id'] ?? null;
        $text = trim($message['text'] ?? '');

        if (!$chatId) {
            return response()->json(['status' => 'error', 'message' => 'No chat ID']);
        }

        if (str_starts_with($text, '/start')) {
            $responseMessage = "Ecliptoon platformasiga xush kelibsiz! 📚\n\nPlatformamizni Telegram ichida ishga tushirish va manhwa/novellalarni to'g'ridan-to'g'ri o'qish uchun quyidagi tugmani bosing:\n\n*Yoki istalgan manhwa nomini yozib yuboring!*";
            $replyMarkup = [
                'inline_keyboard' => [
                    [
                        [
                            'text' => '🚀 Ecliptoon-ni ochish',
                            'web_app' => [
                                'url' => $frontendUrl
                            ]
                        ]
                    ]
                ]
            ];
        } else {
            // Search series by name
            $seriesList = \App\Models\Series::where('title', 'like', "%{$text}%")
                ->orWhere('alternative_titles', 'like', "%{$text}%")
                ->limit(5)
                ->get();

            if ($seriesList->isEmpty()) {
                $responseMessage = "Afsuski, \"{$text}\" nomli manhwa topilmadi. 🔍\n\nPlatformani ochib, barcha manhwa va novellalarni ko'rishingiz mumkin:";
                $replyMarkup = [
                    'inline_keyboard' => [
                        [
                            [
                                'text' => '🚀 Ecliptoon-ni ochish',
                                'web_app' => [
                                    'url' => $frontendUrl
                                ]
                            ]
                        ]
                    ]
                ];
            } else {
                $responseMessage = "Quyidagi manhwalarni topdim: 🔍\n\n";
                $inlineKeyboard = [];
                
                $botUsername = env('TELEGRAM_BOT_USERNAME', 'ecliptoon_bot');
                $appShortName = env('TELEGRAM_APP_SHORT_NAME');

                foreach ($seriesList as $series) {
                    $targetUrl = !empty($appShortName)
                        ? "https://t.me/{$botUsername}/{$appShortName}?startapp=series_{$series->slug}"
                        : "https://t.me/{$botUsername}?startapp=series_{$series->slug}";

                    $responseMessage .= "📚 *{$series->title}*\n";
                    $inlineKeyboard[] = [
                        [
                            'text' => "📖 {$series->title}",
                            'url' => $targetUrl
                        ]
                    ];
                }
                
                // Add mini app button at the end
                $inlineKeyboard[] = [
                    [
                        'text' => '🚀 Ecliptoon-ni ochish',
                        'web_app' => [
                            'url' => $frontendUrl
                        ]
                    ]
                ];

                $replyMarkup = [
                    'inline_keyboard' => $inlineKeyboard
                ];
            }
        }

        $payload = [
            'chat_id' => $chatId,
            'text' => $responseMessage,
            'parse_mode' => 'Markdown',
            'reply_markup' => $replyMarkup
        ];

        $telegramApiUrl = "https://api.telegram.org/bot{$botToken}/sendMessage";
        $response = Http::post($telegramApiUrl, $payload);

        if ($response->failed()) {
            Log::error('Failed to send Telegram message:', [
                'status' => $response->status(),
                'body' => $response->body()
            ]);
            return response()->json(['status' => 'failed', 'error' => $response->body()], 500);
        }

        return response()->json(['status' => 'success']);
    }
}
