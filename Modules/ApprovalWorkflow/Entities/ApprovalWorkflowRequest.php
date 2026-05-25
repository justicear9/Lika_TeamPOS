<?php

namespace Modules\ApprovalWorkflow\Entities;

use App\Transaction;
use App\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ApprovalWorkflowRequest extends Model
{
    protected $table = 'approval_workflow_requests';

    public const STATUS_PENDING = 'pending';

    public const STATUS_APPROVED = 'approved';

    public const STATUS_REJECTED = 'rejected';

    protected $fillable = [
        'business_id',
        'transaction_id',
        'rule_id',
        'status',
        'requested_by',
        'resolved_by',
        'note',
        'payload',
        'resolved_at',
        'stock_reserved',
    ];

    protected $casts = [
        'resolved_at' => 'datetime',
        'payload' => 'array',
        'stock_reserved' => 'boolean',
    ];

    public function transaction(): BelongsTo
    {
        return $this->belongsTo(Transaction::class, 'transaction_id');
    }

    public function rule(): BelongsTo
    {
        return $this->belongsTo(ApprovalWorkflowRule::class, 'rule_id');
    }

    public function requester(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by');
    }

    public function resolver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'resolved_by');
    }
}
