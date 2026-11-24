<?php

namespace App\Models;

use App\Enums\DocumentType;
use App\Enums\Sex;
use App\Enums\SignerRelationship;
use App\Exceptions\ImmutableRecordException;
use App\Models\Concerns\Auditable;
use App\Support\SearchText;
use Carbon\CarbonImmutable;
use Database\Factories\PatientFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * @property int $id
 * @property string $first_name
 * @property string $last_name
 * @property DocumentType $document_type
 * @property string $document_number
 * @property CarbonImmutable $birth_date
 * @property Sex $sex
 * @property string|null $phone
 * @property string|null $phone_alt
 * @property string|null $email
 * @property string|null $address
 * @property string|null $city
 * @property string|null $occupation
 * @property string|null $emergency_contact_name
 * @property string|null $emergency_contact_phone
 * @property string|null $emergency_contact_relationship
 * @property string|null $insurer
 * @property string|null $insurance_number
 * @property string|null $guardian_name
 * @property string|null $guardian_document
 * @property SignerRelationship|null $guardian_relationship
 * @property string|null $guardian_phone
 * @property string|null $administrative_notes
 * @property bool $accepts_communications
 * @property string $search_name
 * @property string|null $search_phone
 * @property int|null $created_by
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 * @property CarbonImmutable|null $deleted_at
 * @property-read string $full_name
 * @property-read int $age
 * @property-read bool $is_minor
 */
class Patient extends Model
{
    /** @use HasFactory<PatientFactory> */
    use Auditable, HasFactory, SoftDeletes;

    public const ADULT_AGE = 18;

    protected $guarded = ['id', 'search_name', 'search_phone', 'created_by', 'created_at', 'updated_at', 'deleted_at'];

    protected function casts(): array
    {
        return [
            'document_type' => DocumentType::class,
            'sex' => Sex::class,
            'guardian_relationship' => SignerRelationship::class,
            'birth_date' => 'date',
            'accepts_communications' => 'boolean',
        ];
    }

    /** @return list<string> */
    protected function auditExclude(): array
    {
        return ['search_name', 'search_phone'];
    }

    protected static function booted(): void
    {
        static::saving(function (Patient $patient) {
            $patient->search_name = SearchText::normalize($patient->first_name.' '.$patient->last_name);
            $phones = trim(SearchText::digits($patient->phone).' '.SearchText::digits($patient->phone_alt));
            $patient->search_phone = $phones === '' ? null : $phones;
        });

        // Regla de negocio: un paciente nunca se borra físicamente.
        static::forceDeleting(fn () => throw ImmutableRecordException::for('Paciente'));
    }

    /** @return Attribute<string, never> */
    protected function fullName(): Attribute
    {
        return Attribute::get(fn () => trim($this->first_name.' '.$this->last_name));
    }

    /** @return Attribute<int, never> */
    protected function age(): Attribute
    {
        return Attribute::get(fn () => (int) $this->birth_date->diffInYears(now()->startOfDay()));
    }

    /** @return Attribute<bool, never> */
    protected function isMinor(): Attribute
    {
        return Attribute::get(fn () => $this->age < self::ADULT_AGE);
    }

    /**
     * Busca por nombre (sin importar acentos ni mayúsculas), documento o teléfono.
     *
     * @param  Builder<Patient>  $query
     */
    public function scopeSearch(Builder $query, ?string $term): void
    {
        $term = trim((string) $term);

        if ($term === '') {
            return;
        }

        $words = array_filter(explode(' ', SearchText::normalize($term)));
        $digits = SearchText::digits($term);

        $query->where(function (Builder $q) use ($words, $digits, $term) {
            if ($words !== []) {
                $q->where(function (Builder $byName) use ($words) {
                    foreach ($words as $word) {
                        $byName->whereRaw("search_name LIKE ? ESCAPE '!'", ['%'.SearchText::escapeLike($word).'%']);
                    }
                });
            }

            $q->orWhereRaw("document_number LIKE ? ESCAPE '!'", [SearchText::escapeLike($term).'%']);

            if (strlen($digits) >= 4) {
                $q->orWhere('search_phone', 'like', '%'.$digits.'%');
            }
        });
    }

    /** @return HasMany<MedicalHistory, $this> */
    public function medicalHistories(): HasMany
    {
        return $this->hasMany(MedicalHistory::class);
    }

    /** @return HasOne<MedicalHistory, $this> */
    public function currentMedicalHistory(): HasOne
    {
        return $this->hasOne(MedicalHistory::class)->latestOfMany('version');
    }

    /** @return HasMany<Allergy, $this> */
    public function allergies(): HasMany
    {
        return $this->hasMany(Allergy::class);
    }

    /** @return HasMany<PatientCondition, $this> */
    public function conditions(): HasMany
    {
        return $this->hasMany(PatientCondition::class);
    }

    /** @return HasMany<ToothEvent, $this> */
    public function toothEvents(): HasMany
    {
        return $this->hasMany(ToothEvent::class);
    }

    /** @return HasMany<TreatmentPlan, $this> */
    public function treatmentPlans(): HasMany
    {
        return $this->hasMany(TreatmentPlan::class);
    }

    /** @return HasMany<Payment, $this> */
    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }

    /** @return HasMany<Consultation, $this> */
    public function consultations(): HasMany
    {
        return $this->hasMany(Consultation::class);
    }

    /** @return HasMany<Document, $this> */
    public function documents(): HasMany
    {
        return $this->hasMany(Document::class);
    }

    /** @return HasMany<LabOrder, $this> */
    public function labOrders(): HasMany
    {
        return $this->hasMany(LabOrder::class);
    }

    /** @return HasMany<Prescription, $this> */
    public function prescriptions(): HasMany
    {
        return $this->hasMany(Prescription::class);
    }

    /** @return HasMany<PeriodontalRecord, $this> */
    public function periodontalRecords(): HasMany
    {
        return $this->hasMany(PeriodontalRecord::class);
    }

    /** @return HasOne<PatientAccount, $this> */
    public function portalAccount(): HasOne
    {
        return $this->hasOne(PatientAccount::class);
    }

    /** @return HasMany<Appointment, $this> */
    public function appointments(): HasMany
    {
        return $this->hasMany(Appointment::class);
    }

    /** @return HasMany<ConsentSignature, $this> */
    public function consentSignatures(): HasMany
    {
        return $this->hasMany(ConsentSignature::class);
    }
}
