@extends('layouts.app')
@section('title', __('inventoryreporting::lang.orphan_mapping_audit'))

@section('content')
    <section class="content-header">
        <h1 class="tw-text-xl md:tw-text-3xl tw-font-bold tw-text-black">@lang('inventoryreporting::lang.orphan_mapping_audit')</h1>
        <p class="text-muted">@lang('inventoryreporting::lang.orphan_mapping_help')</p>
    </section>

    <section class="content">
        @component('components.widget', ['class' => 'box-solid'])
            {!! Form::open(['url' => route('inventoryreporting.integrity.orphans'), 'method' => 'get']) !!}
            <div class="row">
                <div class="col-sm-4">
                    <div class="form-group">
                        {!! Form::label('location_id', __('purchase.business_location') . ':') !!}
                        {!! Form::select('location_id', $business_locations, $locationId, ['class' => 'form-control select2', 'placeholder' => __('messages.all')]) !!}
                    </div>
                </div>
                <div class="col-sm-8">
                    <div class="form-group" style="margin-top: 25px;">
                        <button type="submit" class="tw-dw-btn tw-dw-btn-primary">@lang('report.filters')</button>
                    </div>
                </div>
            </div>
            {!! Form::close() !!}
            @if($rows->count() > 0)
                {!! Form::open(['url' => route('inventoryreporting.integrity.orphans.repair'), 'method' => 'post']) !!}
                    {!! Form::hidden('location_id', $locationId) !!}
                    <button type="submit" class="tw-dw-btn tw-dw-btn-error tw-text-white" onclick="return confirm('@lang('inventoryreporting::lang.orphan_mapping_repair_confirm')')">
                        @lang('inventoryreporting::lang.orphan_mapping_repair')
                    </button>
                {!! Form::close() !!}
            @endif
        @endcomponent

        @component('components.widget', ['class' => 'box-solid'])
            <div class="table-responsive">
                <table class="table table-bordered table-striped">
                    <thead>
                        <tr>
                            <th>@lang('inventoryreporting::lang.mapping_id')</th>
                            <th>@lang('inventoryreporting::lang.missing_sell_line_id')</th>
                            <th>@lang('inventoryreporting::lang.purchase_line_id')</th>
                            <th>@lang('inventoryreporting::lang.product')</th>
                            <th>@lang('inventoryreporting::lang.sku')</th>
                            <th>@lang('inventoryreporting::lang.mapping_quantity')</th>
                            <th>@lang('inventoryreporting::lang.export_location')</th>
                        </tr>
                    </thead>
                    <tbody>
                    @forelse($rows as $row)
                        <tr>
                            <td>{{ $row->mapping_id }}</td>
                            <td>{{ $row->sell_line_id }}</td>
                            <td>{{ $row->purchase_line_id }}</td>
                            <td>{{ $row->product_name }}</td>
                            <td>{{ $row->sub_sku }}</td>
                            <td>{{ @num_format($row->mapping_quantity) }}</td>
                            <td>{{ $row->location_name }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="text-center">@lang('inventoryreporting::lang.orphan_mapping_no_rows')</td>
                        </tr>
                    @endforelse
                    </tbody>
                </table>
            </div>
        @endcomponent
    </section>
@endsection
