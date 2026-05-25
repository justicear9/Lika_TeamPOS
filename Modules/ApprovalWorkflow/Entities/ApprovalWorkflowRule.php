<?php

namespace Modules\ApprovalWorkflow\Entities;

use App\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ApprovalWorkflowRule extends Model
{
    protected $table = 'approval_workflow_rules';

    protected $fillable = [
        'business_id',
        'transaction_type',
        'is_enabled',
        'metadata',
    ];

    protected $casts = [
        'is_enabled' => 'boolean',
        'metadata' => 'array',
    ];

    public function approvers(): BelongsToMany
    {
        return $this->belongsToMany(
            User::class,
            'approval_workflow_approvers',
            'rule_id',
            'user_id'
        )->withTimestamps();
    }

    public function requests(): HasMany
    {
        return $this->hasMany(ApprovalWorkflowRequest::class, 'rule_id');
    }
}
