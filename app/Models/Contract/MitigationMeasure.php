<?php

namespace App\Models\Contract;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

use App\Models\DocumentAnalytics;

class MitigationMeasure extends Model
{
    use HasFactory;

    protected $fillable = [
        'document_analytics_id',
        'mitigation_measure',
        'thumbs'
    ];

    /**
     * Get the document analytic according to one mitigation measure...
     */
    public function document_analytics(): BelongsTo
    {
        return $this->belongsTo(DocumentAnalytics::class);
    }
}
