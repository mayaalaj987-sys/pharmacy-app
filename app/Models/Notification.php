<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Notification extends Model
{
    use HasFactory;

    protected $table = 'notifications';

    protected $fillable = ['pharmacy_id', 'title', 'message', 'type', 'is_read', 'date'];

    protected function casts(): array
    {
        return [
            'is_read' => 'boolean',
            'date' => 'date',
        ];
    }

    public function pharmacy()
    {
        return $this->belongsTo(Pharmacy::class);
    }

    // helper to create + could be extended to push via broadcasting later
    public static function notify(int $pharmacyId, string $title, string $message, string $type): self
    {
        return self::create([
            'pharmacy_id' => $pharmacyId,
            'title' => $title,
            'message' => $message,
            'type' => $type,
            'is_read' => false,
            'date' => now()->toDateString(),
        ]);
    }
}
