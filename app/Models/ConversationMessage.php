<?php

namespace App\Models;

use App\Services\Conversation\IncomingMessage;
use App\Services\Conversation\Reply;
use Database\Factories\ConversationMessageFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Una riga della chat WhatsApp: `in` = scritto dall'agente, `out` = inviato dal bot. Il testo è cifrato. */
class ConversationMessage extends Model
{
    /** @use HasFactory<ConversationMessageFactory> */
    use HasFactory;

    public const IN = 'in';

    public const OUT = 'out';

    protected $guarded = [];

    protected function casts(): array
    {
        return ['body' => 'encrypted'];
    }

    public function conversation(): BelongsTo
    {
        return $this->belongsTo(Conversation::class);
    }

    public static function recordIncoming(IncomingMessage $message): self
    {
        $text = match ($message->type) {
            'media' => trim('[Allegato '.($message->mime ?? '').'] '.($message->text ?? '')),
            'unsupported' => '[Messaggio non supportato]',
            default => (string) $message->text,
        };

        return self::record($message->from, self::IN, $text);
    }

    public static function recordOutgoing(string $to, Reply $reply): self
    {
        $options = collect($reply->options)->map(fn ($title) => '▫ '.$title)->implode("\n");

        return self::record($to, self::OUT, trim($reply->body.($options !== '' ? "\n\n".$options : '')));
    }

    private static function record(string $waNumber, string $direction, string $body): self
    {
        return self::create([
            'conversation_id' => Conversation::where('wa_number', $waNumber)->latest('id')->value('id'),
            'wa_number' => $waNumber,
            'direction' => $direction,
            'body' => $body,
        ]);
    }
}
