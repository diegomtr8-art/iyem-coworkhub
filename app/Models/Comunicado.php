<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Comunicado extends Model
{
    protected $table = 'comunicados';

    protected $fillable = ['user_id', 'titulo', 'mensaje', 'tipo', 'leido'];

    protected $casts = ['leido' => 'boolean'];

    public function user() { return $this->belongsTo(User::class); }
}
