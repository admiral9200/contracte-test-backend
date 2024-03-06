<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

use App\Models\Contract\MitigationMeasure;
use App\Models\Contract\RiskDefinition;

class DocumentAnalytics extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'file_name',
        'category',
        'risky_sentence',
        'risk_definition',
        'probability',
        'impact_on_client',
        'mitigation_measure',
        'probability_after_mitigation',
        'average_risk_score'
    ];


    
    /**
     * Get all of the mitigation_measures for the DocumentAnalytics
     *
     * @return \Illuminate\Database\Eloquent\Relations\HasMany
     */
    public function mitigation_measures(): HasMany
    {
        return $this->hasMany(MitigationMeasure::class);
    }



    /**
     * Get all of the risk_definitions for the DocumentAnalytics
     *
     * @return \Illuminate\Database\Eloquent\Relations\HasMany
     */
    public function risk_definitions(): HasMany
    {
        return $this->hasMany(RiskDefinition::class);
    }
}
