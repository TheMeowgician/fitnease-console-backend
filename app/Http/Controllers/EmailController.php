<?php

namespace App\Http\Controllers;

use App\Services\AuditService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class EmailController extends Controller
{
    public function send(Request $request): JsonResponse
    {
        $request->validate([
            'subject' => 'required|string|max:200',
            'body' => 'required|string',
            'recipients' => 'required|array|min:1',
            'recipients.*' => 'required|email',
            'button_text' => 'nullable|string|max:100',
            'button_url' => 'nullable|string',
        ]);

        $subject = $request->subject;
        $bodyHtml = nl2br(e($request->body));
        $recipients = $request->recipients;
        $buttonText = $request->button_text;
        $buttonUrl = $request->button_url;

        $html = view('emails.broadcast', [
            'subject' => $subject,
            'body' => $bodyHtml,
            'buttonText' => $buttonText,
            'buttonUrl' => $buttonUrl,
        ])->render();

        $sent = 0;
        $failed = [];

        foreach ($recipients as $email) {
            try {
                Mail::html($html, function ($message) use ($email, $subject) {
                    $message->to($email)->subject($subject);
                });
                $sent++;
            } catch (\Exception $e) {
                Log::error("Email send failed to {$email}", ['error' => $e->getMessage()]);
                $failed[] = $email;
            }
        }

        AuditService::log('send_email', 'email', null, [
            'subject' => $subject,
            'total_recipients' => count($recipients),
            'sent' => $sent,
            'failed' => $failed,
        ]);

        return response()->json([
            'message' => "{$sent} email(s) sent successfully.",
            'sent' => $sent,
            'failed' => $failed,
        ]);
    }

    public function preview(Request $request): JsonResponse
    {
        $request->validate([
            'subject' => 'required|string',
            'body' => 'required|string',
            'button_text' => 'nullable|string',
            'button_url' => 'nullable|string',
        ]);

        $html = view('emails.broadcast', [
            'subject' => $request->subject,
            'body' => nl2br(e($request->body)),
            'buttonText' => $request->button_text,
            'buttonUrl' => $request->button_url,
        ])->render();

        return response()->json(['html' => $html]);
    }
}
