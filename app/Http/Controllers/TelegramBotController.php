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

        if (!isset($update['message'])) {
            return response()->json(['status' => 'ignored']);
        }

        $message = $update['message'];
        $chatId = $message['chat']['id'] ?? null;
        $text = $message['text'] ?? '';

        if (!$chatId) {
            return response()->json(['status' => 'error', 'message' => 'No chat ID']);
        }

        $botToken = env('TELEGRAM_BOT_TOKEN');
        if (!$botToken) {
            Log::error('TELEGRAM_BOT_TOKEN is not configured in .env');
            return response()->json(['status' => 'error', 'message' => 'Bot token missing'], 500);
        }

        $frontendUrl = env('TELEGRAM_MINI_APP_URL', 'https://ecliptoon.uz');

        // Respond to commands (like /start) or any text message
        $responseMessage = "Ecliptoon platformasiga xush kelibsiz! 📚\n\nPlatformamizni Telegram ichida ishga tushirish va manhwa/novellalarni to'g'ridan-to'g'ri o'qish uchun quyidagi tugmani bosing:";
        
        $payload = [
            'chat_id' => $chatId,
            'text' => $responseMessage,
            'reply_markup' => [
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
            ]
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
