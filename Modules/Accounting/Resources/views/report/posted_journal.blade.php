@extends('layouts.app')

@section('title', 'Posted Journal Report')

@section('content')
@include('accounting::layouts.nav')

<section class="content">
    @component('components.filters', ['title' => __('report.filters')])
        {!! Form::open(['url' => route('accounting.postedJournal'), 'method' => 'get']) !!}
            <div class="row">
                <div class="col-md-3">
                    <div class="form-group">
                        {!! Form::label('date_range_filter', __('report.date_range') . ':') !!}
                        {!! Form::text('date_range_filter', null, ['placeholder' => __('lang_v1.select_a_date_range'), 'class' => 'form-control', 'readonly', 'id' => 'date_range_filter']) !!}
                        {!! Form::hidden('start_date', $start_date, ['id' => 'start_date']) !!}
                        {!! Form::hidden('end_date', $end_date, ['id' => 'end_date']) !!}
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="form-group">
                        {!! Form::label('account_id', __('accounting::lang.account') . ':') !!}
                        {!! Form::select('account_id', $accounts, $account_id, ['class' => 'form-control select2', 'placeholder' => __('lang_v1.all'), 'style' => 'width:100%']) !!}
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="form-group">
                        {!! Form::label('balancing_account_id', 'Balancing Account:') !!}
                        {!! Form::select('balancing_account_id', $accounts, $balancing_account_id, ['class' => 'form-control select2', 'placeholder' => __('lang_v1.all'), 'style' => 'width:100%']) !!}
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="form-group">
                        {!! Form::label('per_page', 'Rows per page:') !!}
                        {!! Form::select('per_page', [25 => '25', 50 => '50', 100 => '100', 200 => '200'], $per_page, ['class' => 'form-control', 'id' => 'per_page']) !!}
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="form-group">
                        {!! Form::label('search', __('lang_v1.search') . ':') !!}
                        {!! Form::text('search', $search, ['class' => 'form-control', 'placeholder' => 'Reference, account, GL code, memo, notes...']) !!}
                    </div>
                </div>
            </div>
            <button type="submit" class="tw-dw-btn tw-dw-btn-primary">@lang('report.apply_filters')</button>
            <a href="{{ route('accounting.postedJournal') }}" class="tw-dw-btn tw-dw-btn-outline">@lang('lang_v1.clear')</a>
            <a href="{{ route('accounting.postedJournalExport', request()->query()) }}" class="tw-dw-btn tw-dw-btn-success">@lang('accounting::lang.export_to_excel')</a>
        {!! Form::close() !!}
    @endcomponent

    @component('components.widget', ['class' => 'box-solid'])
        <div class="box-header with-border text-center">
            <h2 class="box-title">Posted Journal Report</h2>
            <p>{{ @format_date($start_date) }} ~ {{ @format_date($end_date) }}</p>
        </div>
        <div class="table-responsive">
            <table class="table table-bordered table-striped">
                <thead>
                    <tr>
                        <th>@lang('messages.date')</th>
                        <th>@lang('purchase.ref_no')</th>
                        <th>GL Code</th>
                        <th>@lang('accounting::lang.account')</th>
                        <th>@lang('accounting::lang.debit')</th>
                        <th>@lang('accounting::lang.credit')</th>
                        <th>Memo</th>
                        <th>Balancing Account</th>
                        <th>Balancing GL</th>
                        <th>@lang('lang_v1.additional_notes')</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($rows as $row)
                        <tr>
                            <td>{{ @format_datetime($row->operation_date) }}</td>
                            <td>{{ $row->ref_no }}</td>
                            <td>{{ $row->account_gl_code }}</td>
                            <td>{{ $row->account_name }}</td>
                            <td>
                                @if($row->type === 'debit')
                                    @format_currency($row->amount)
                                @endif
                            </td>
                            <td>
                                @if($row->type === 'credit')
                                    @format_currency($row->amount)
                                @endif
                            </td>
                            <td>{{ $row->memo }}</td>
                            <td>{{ $row->balancing_account }}</td>
                            <td>{{ $row->balancing_gl_code }}</td>
                            <td>{{ $row->additional_notes }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="10" class="text-center">@lang('accounting::lang.no_records')</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="box-footer clearfix">
            <div class="pull-left">
                @if($rows->total() > 0)
                    Showing {{ $rows->firstItem() }} to {{ $rows->lastItem() }} of {{ $rows->total() }} entries
                @endif
            </div>
            <div class="pull-right">
                {{ $rows->appends(request()->query())->links() }}
            </div>
        </div>
    @endcomponent
</section>
@endsection

@section('javascript')
<script type="text/javascript">
    $(document).ready(function () {
        dateRangeSettings.startDate = moment('{{ $start_date }}');
        dateRangeSettings.endDate = moment('{{ $end_date }}');

        $('#date_range_filter').daterangepicker(
            dateRangeSettings,
            function (start, end) {
                $('#date_range_filter').val(start.format(moment_date_format) + ' ~ ' + end.format(moment_date_format));
                $('#start_date').val(start.format('YYYY-MM-DD'));
                $('#end_date').val(end.format('YYYY-MM-DD'));
            }
        );

        $('#date_range_filter').val(
            moment('{{ $start_date }}').format(moment_date_format) + ' ~ ' + moment('{{ $end_date }}').format(moment_date_format)
        );
    });
</script>
@endsection
