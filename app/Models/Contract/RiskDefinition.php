<?php

namespace App\Models\Contract;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

use App\Models\DocumentAnalytics;

class RiskDefinition extends Model
{
    use HasFactory;

    protected $fillable = [
        'document_analytics_id',
        'risk_definition',
        'thumbs'
    ];

    /**
     * Get the document analytics according to a Risk definition...
     */
    public function document_analytics(): BelongsTo
    {
        return $this->belongsTo(DocumentAnalytics::class);
    }
}
