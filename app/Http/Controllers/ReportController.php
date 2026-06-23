<?php

namespace App\Http\Controllers;

use App\Models\Report;
use Illuminate\Http\Request;

class ReportController extends Controller
{
    /**
     * Submit an issue report.
     */
    public function store(Request $request)
    {
        $request->validate([
            'message' => 'required|string|min:10',
            'series_id' => 'nullable|exists:series,id',
            'chapter_id' => 'nullable|exists:chapters,id',
            'captcha_val1' => 'required|integer',
            'captcha_val2' => 'required|integer',
            'captcha_answer' => 'required|integer',
        ]);

        // Verify mathematical captcha
        if (($request->captcha_val1 + $request->captcha_val2) !== $request->captcha_answer) {
            return response()->json([
                'errors' => [
                    'captcha_answer' => ['Kaptcha javobi noto\'g\'ri. Iltimos, qaytadan hisoblang.'] // "Captcha answer incorrect. Please recalculate."
                ]
            ], 422);
        }

        $user = $request->user('sanctum');

        $report = Report::create([
            'user_id' => $user ? $user->id : null,
            'series_id' => $request->series_id,
            'chapter_id' => $request->chapter_id,
            'message' => $request->message,
            'status' => 'pending',
        ]);

        return response()->json([
            'message' => 'Hisobot muvaffaqiyatli yuborildi. Muammoni ko\'rib chiqamiz.', // "Report successfully submitted. We will look into it."
            'report' => $report
        ], 201);
    }
}
