<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    use HasFactory, Notifiable;

    protected $fillable = ['name', 'username', 'email', 'role', 'active', 'password'];

    protected $hidden = ['password', 'remember_token'];

    protected function casts(): array
    {
        return ['password' => 'hashed', 'active' => 'boolean'];
    }

    public function isOwner(): bool
    {
        return $this->role === 'owner';
    }

    public function roleLabel(): string
    {
        return $this->isOwner() ? 'Owner / Admin' : 'Petugas Persediaan';
    }
}
