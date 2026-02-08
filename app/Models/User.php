<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

use Filament\Models\Contracts\FilamentUser;

class User extends Authenticatable implements FilamentUser
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasFactory, Notifiable, \Spatie\Permission\Traits\HasRoles, \Laravel\Sanctum\HasApiTokens;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
        'credit_balance',
        'total_profit_usd',
    ];

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
            'credit_balance' => 'decimal:2',
            'total_profit_usd' => 'decimal:6',
        ];
    }

    public function transactions()
    {
        return $this->hasMany(CreditTransaction::class);
    }

    public function generations()
    {
        return $this->hasMany(Generation::class);
    }

    public function canAccessPanel(\Filament\Panel $panel): bool
    {
        // Allow access if user has 'admin' role OR has specific email
        // Also check if role is seeded before checking role to avoid errors in fresh install
        if ($this->hasRole('admin')) {
            return true;
        }

        return in_array($this->email, [
            'admin@kolayzeka.com',
            'mehtap@kolayzeka.com',
            'burak@kolayzeka.com', // Added for potential dev access
        ]);
    }
}
