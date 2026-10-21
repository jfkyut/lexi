<?php

namespace App\Http\Controllers;

use App\Models\Attachment;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class AttachmentController extends Controller
{
    public function show(Request $request, Attachment $attachment): StreamedResponse
    {
        abort_unless($request->user()?->id === $attachment->message->thread->user_id, 404);

        return Storage::disk('local')->download($attachment->path, $attachment->filename);
    }
}
