<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use App\Policies\UserPolicy;
use Illuminate\Database\Eloquent\Attributes\UsePolicy;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

#[UsePolicy(UserPolicy::class)]
class User extends Authenticatable
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasFactory, Notifiable;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    public const KYC_NOT_SUBMITTED = 'not_submitted';

    public const KYC_PENDING_REVIEW = 'pending_review';

    public const KYC_APPROVED = 'approved';

    public const KYC_REJECTED = 'rejected';

    /** @var list<string> */
    public const MONTHLY_REVENUE_RANGES = [
        'up_to_10k',
        '10k_50k',
        '50k_100k',
        '100k_500k',
        'over_500k',
    ];

    protected $fillable = [
        'name',
        'email',
        'username',
        'avatar',
        'password',
        'role',
        'tenant_id',
        'team_role_id',
        'person_type',
        'document',
        'account_status',
        'merchant_fees',
        'merchant_settlement_overrides',
        'merchant_gateway_order',
        'payout_settings',
        'birth_date',
        'company_name',
        'legal_representative_cpf',
        'address_zip',
        'address_street',
        'address_number',
        'address_complement',
        'address_neighborhood',
        'address_city',
        'address_state',
        'monthly_revenue_range',
        'kyc_status',
        'kyc_rejection_reason',
        'kyc_reviewed_at',
        'kyc_reviewed_by',
    ];

    /** @deprecated Migração: antigo admin virou infoprodutor; manter só por compatibilidade de dados legados */
    public const ROLE_ADMIN = 'admin';

    public const ROLE_PLATFORM_ADMIN = 'platform_admin';

    public const ROLE_INFOPRODUTOR = 'infoprodutor';

    public const ROLE_ALUNO = 'aluno';

    public const ROLE_TEAM = 'team';

    public function isAdmin(): bool
    {
        return $this->role === self::ROLE_ADMIN;
    }

    public function isPlatformAdmin(): bool
    {
        return $this->role === self::ROLE_PLATFORM_ADMIN;
    }

    public function isInfoprodutor(): bool
    {
        return $this->role === self::ROLE_INFOPRODUTOR;
    }

    public function isAluno(): bool
    {
        return $this->role === self::ROLE_ALUNO;
    }

    public function isTeam(): bool
    {
        return $this->role === self::ROLE_TEAM;
    }

    /**
     * Painel do vendedor (checkout, produtos, vendas).
     * Não inclui platform_admin nem admin global (estes usam /plataforma).
     */
    public function canAccessSellerPanel(): bool
    {
        if ($this->isPlatformAdmin()) {
            return false;
        }
        if ($this->isAdmin()) {
            return false;
        }

        return $this->isInfoprodutor() || $this->isTeam();
    }

    /**
     * Compat: painel clássico Getfy (mesmo que vendedor; operador usa /plataforma).
     */
    public function canAccessPanel(): bool
    {
        return $this->canAccessSellerPanel();
    }

    /**
     * Painel operador da plataforma (/plataforma): platform_admin sem tenant, ou papel admin global.
     */
    public function canAccessPlatformPanel(): bool
    {
        if ($this->role === self::ROLE_PLATFORM_ADMIN) {
            return $this->tenant_id === null;
        }

        return $this->role === self::ROLE_ADMIN;
    }

    public function teamRole(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(TeamRole::class, 'team_role_id');
    }

    public function products(): \Illuminate\Database\Eloquent\Relations\BelongsToMany
    {
        return $this->belongsToMany(Product::class, 'product_user')->withTimestamps();
    }

    public function subscriptions(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(Subscription::class);
    }

    public function savedPaymentMethods(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(SavedPaymentMethod::class);
    }

    public function kycDocuments(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(KycDocument::class);
    }

    public function kycReviewer(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(User::class, 'kyc_reviewed_by');
    }

    /**
     * Usuário dono do tenant para checagem de KYC (infoprodutor ou dono quando equipe).
     */
    public function kycSubjectUser(): self
    {
        if ($this->isTeam() && $this->tenant_id) {
            $owner = static::query()->find($this->tenant_id);

            return $owner instanceof self ? $owner : $this;
        }

        return $this;
    }

    public function hasApprovedKyc(): bool
    {
        $status = $this->kycSubjectUser()->kyc_status;

        return $status === self::KYC_APPROVED;
    }

    /**
     * Conta do negócio suspensa/bloqueada/rejeitada — equipe segue o titular (kycSubjectUser).
     */
    public function sellerAccountAccessBlocked(): bool
    {
        if (! $this->canAccessSellerPanel()) {
            return false;
        }

        $subject = $this->kycSubjectUser();
        $status = (string) ($subject->account_status ?? 'approved');

        return in_array($status, ['suspended', 'blocked', 'rejected'], true);
    }

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'merchant_fees' => 'array',
            'merchant_settlement_overrides' => 'array',
            'merchant_gateway_order' => 'array',
            'payout_settings' => 'array',
            'birth_date' => 'date',
            'kyc_reviewed_at' => 'datetime',
        ];
    }
}
