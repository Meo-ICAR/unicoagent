<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCompany;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Query\Expression;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Unico\Core\Models\Fornitore as CoreFornitore;
use Unico\Core\Signature\PhoneNumber;

/**
 * Anagrafica degli agenti / collaboratori (tabella `fornitoris` su unico-core).
 */
class Fornitore extends CoreFornitore
{
    use BelongsToCompany;

    protected $guarded = [];

    protected function casts(): array
    {
        return array_merge(parent::casts(), [
            'issubfornitore' => 'boolean',
        ]);
    }

    /**
     * La sigla del produttore: quella inserita oppure, se manca, le iniziali del nome (o le prime tre lettere
     * se il nome è una parola sola). Senza nome, «SEG» (segnalatore). Una volta ricavata viene salvata.
     */
    public function ensureSigla(): string
    {
        if (filled($this->sigla)) {
            return $this->sigla;
        }

        $name = Str::upper(Str::ascii((string) $this->display_name));
        $words = preg_split('/[^A-Z0-9]+/', $name, -1, PREG_SPLIT_NO_EMPTY);
        $sigla = match (true) {
            $words === [] => 'SEG',
            count($words) === 1 => substr($words[0], 0, 3),
            default => implode('', array_map(fn ($w) => $w[0], array_slice($words, 0, 3))),
        };

        $this->forceFill(['sigla' => $sigla])->saveQuietly();

        return $sigla;
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class, static::companyColumn());
    }

    /** Agenti attualmente convenzionati (esclude cessati e cancellati). */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    /** Denominazione, oppure il nome del referente se manca. */
    public function getDisplayNameAttribute(): ?string
    {
        return filled($this->name) ? $this->name : $this->nome;
    }

    /**
     * Nome del produttore (denominazione, altrimenti referente) da anteporre al numero WhatsApp.
     * Vale anche per chi non è più attivo: in email serve comunque a riconoscerlo.
     */
    public static function whatsAppLabel(string $waNumber): string
    {
        $wanted = self::nationalNumber($waNumber);
        if ($wanted === '') {
            return $waNumber;
        }

        $name = static::query()->whereNotNull('tel')->get()
            ->filter(fn (self $f) => self::nationalNumber((string) $f->tel) === $wanted)
            ->sortBy(fn (self $f) => $f->is_active ? 0 : 1)
            ->map(fn (self $f) => $f->display_name)
            ->first(fn (?string $name) => filled($name));

        return filled($name) ? $name.' '.$waNumber : $waNumber;
    }

    /** L'agente attivo il cui telefono coincide con il numero WhatsApp, in qualunque formato siano scritti. */
    public static function findByWhatsApp(string $waNumber): ?self
    {
        $wanted = self::nationalNumber($waNumber);
        if ($wanted === '') {
            return null;
        }

        return static::active()->whereNotNull('tel')->get()
            ->first(fn (self $f) => self::nationalNumber($f->tel) === $wanted);
    }

    public const OCCASIONAL_TYPE = 'Segnalatore occasionale';

    /** Chi scrive è un produttore convenzionato (attivo)? */
    public static function isProducer(string $waNumber): bool
    {
        return self::findByWhatsApp($waNumber) !== null;
    }

    /**
     * Registra un numero sconosciuto come segnalatore occasionale (non attivo), una volta sola.
     * Un numero già presente, anche non attivo, non si duplica.
     */
    public static function registerOccasional(string $waNumber): self
    {
        $wanted = self::nationalNumber($waNumber);
        $existing = static::withTrashed()->whereNotNull('tel')->get()->first(fn (self $f) => self::nationalNumber($f->tel) === $wanted);

        return $existing ?? static::create(['tel' => $waNumber, 'type' => self::OCCASIONAL_TYPE, 'is_active' => false]);
    }

    public const DEMO_TYPE = 'Demo';

    /** Demo: il numero lavora con quella company. Un fornitore esistente (anche non attivo) cambia company; altrimenti se ne crea uno «Demo». */
    public static function assignToCompany(string $waNumber, int $companyId): self
    {
        $wanted = self::nationalNumber($waNumber);
        $existing = static::withTrashed()->whereNotNull('tel')->get()->first(fn (self $f) => self::nationalNumber($f->tel) === $wanted);

        if ($existing) {
            $existing->deleted_at = null;
            $existing->forceFill([static::companyColumn() => $companyId, 'is_active' => true])->save();

            return $existing;
        }

        return static::create(['tel' => $waNumber, 'name' => 'Demo '.$wanted, 'type' => self::DEMO_TYPE, 'is_active' => true, static::companyColumn() => $companyId]);
    }

    /** Il nome del produttore con lo stesso telefono (ultime 10 cifre) del numero WhatsApp della colonna: per ordinare le tabelle. */
    public static function nameByWhatsAppSql(string $waColumn): Expression
    {
        $fornitore = new static;
        $table = $fornitore->getConnection()->getDatabaseName().'.'.$fornitore->getTable();

        return DB::raw("(select min(f.name) from {$table} f where f.deleted_at is null and f.tel is not null and right(regexp_replace(f.tel, '[^0-9]', ''), 10) = right({$waColumn}, 10))");
    }

    /** Limita le righe a quelle il cui numero WhatsApp (colonna) è il telefono del produttore indicato. */
    public static function whereWhatsAppOf(Builder $query, string $waColumn, mixed $fornitoreId): Builder
    {
        if (blank($fornitoreId)) {
            return $query;
        }

        $national = self::nationalNumber((string) static::find($fornitoreId)?->tel);

        return $query->where($waColumn, 'like', '%'.($national !== '' ? $national : '#nessuno#'));
    }

    /** Solo cifre, senza prefisso internazionale italiano (+39 / 0039). */
    public static function nationalNumber(string $phone): string
    {
        return PhoneNumber::national($phone);
    }
}
