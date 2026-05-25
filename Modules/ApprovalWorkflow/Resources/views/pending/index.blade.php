@extends('layouts.app')
@section('title', __('approvalworkflow::lang.pending_approvals'))

@section('content')
    <section class="content-header">
        <h1>@lang('approvalworkflow::lang.pending_approvals')</h1>
    </section>

    <section class="content">
        @component('components.widget', ['class' => 'box-primary'])
            @if($transactions->isEmpty())
                <p class="text-muted">@lang('approvalworkflow::lang.no_pending')</p>
            @else
                <div class="table-responsive">
                    <table class="table table-condensed">
                        <thead>
                        <tr>
                            <th>@lang('approvalworkflow::lang.th_type')</th>
                            <th>@lang('approvalworkflow::lang.th_ref')</th>
                            <th>@lang('approvalworkflow::lang.th_date')</th>
                            <th>@lang('approvalworkflow::lang.th_contact')</th>
                            <th class="text-right">@lang('approvalworkflow::lang.th_amount')</th>
                            <th>@lang('approvalworkflow::lang.actions')</th>
                        </tr>
                        </thead>
                        <tbody>
                        @foreach($transactions as $t)
                            @php($view = \Modules\ApprovalWorkflow\Services\ApprovalTransactionLink::viewTarget($t))
                            <tr>
                                <td>{{ __('approvalworkflow::lang.type_' . $t->type) }}</td>
                                <td>
                                    @if($t->type === 'purchase' || $t->type === 'stock_adjustment')
                                        {{ $t->ref_no }}
                                    @elseif($t->type === 'sell_return')
                                        {{ $t->invoice_no }}
                                    @else
                                        {{ $t->invoice_no }}
                                    @endif
                                </td>
                                <td>{{ @format_date($t->transaction_date) }}</td>
                                <td>{{ $t->contact?->name ?? '—' }}</td>
                                <td class="text-right">
                                    <span class="display_currency" data-currency_symbol="true" data-orig_value="{{ $t->final_total }}">
                                        {{ $t->final_total }}
                                    </span>
                                </td>
                                <td class="text-nowrap" style="min-width: 220px;">
                                    <div class="tw-flex tw-flex-wrap tw-items-center tw-gap-1" style="max-width: 100%;">
                                        @if($view['modal'])
                                            <a href="#"
                                               class="btn btn-sm btn-default btn-modal tw-whitespace-nowrap"
                                               data-href="{{ $view['url'] }}"
                                               data-container="{{ $view['container'] }}">
                                                <i class="fas fa-eye" aria-hidden="true"></i> @lang('approvalworkflow::lang.action_view')
                                            </a>
                                        @else
                                            <a href="{{ $view['url'] }}"
                                               class="btn btn-sm btn-default tw-whitespace-nowrap">
                                                <i class="fas fa-eye" aria-hidden="true"></i> @lang('approvalworkflow::lang.action_view')
                                            </a>
                                        @endif
                                        {!! Form::open(['url' => action([\Modules\ApprovalWorkflow\Http\Controllers\ApprovalActionController::class, 'approve'], [$t->id]), 'method' => 'post', 'style' => 'display:inline;margin:0;']) !!}
                                        @csrf
                                        <button type="submit" class="btn btn-sm btn-success tw-whitespace-nowrap">
                                            <i class="fas fa-check" aria-hidden="true"></i> @lang('approvalworkflow::lang.approve')
                                        </button>
                                        {!! Form::close() !!}
                                        {!! Form::open(['url' => action([\Modules\ApprovalWorkflow\Http\Controllers\ApprovalActionController::class, 'reject'], [$t->id]), 'method' => 'post', 'style' => 'display:inline;margin:0;', 'onSubmit' => "return confirm('".e(__('approvalworkflow::lang.reject_confirm'))."')"]) !!}
                                        @csrf
                                        <button type="submit" class="btn btn-sm btn-danger tw-whitespace-nowrap">
                                            <i class="fas fa-times" aria-hidden="true"></i> @lang('approvalworkflow::lang.reject')
                                        </button>
                                        {!! Form::close() !!}
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                        </tbody>
                    </table>
                </div>
                {{ $transactions->links() }}
            @endif
        @endcomponent
    </section>
@endsection
