<?php

namespace App\Models;

use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Admin extends Authenticatable
{
    use HasFactory;

    protected $fillable = ['username', 'password_hash'];

    protected $hidden = ['password_hash'];

    public $timestamps = true;

    // Laravel espera 'password' por padrão; mapeamos para password_hash
    public function getAuthPassword(): string
    {
        return $this->password_hash;
    }

    // Informa ao Laravel o nome correto da coluna para operações automáticas (como o "rehash")
    public function getAuthPasswordName(): string
    {
        return 'password_hash';
    }
}
