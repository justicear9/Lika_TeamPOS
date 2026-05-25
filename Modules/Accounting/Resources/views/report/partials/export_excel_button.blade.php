<a href="{{ route($export_route, request()->only(['location_id'])) }}" class="tw-dw-btn tw-dw-btn-success tw-dw-btn-sm">
    <i class="fa fa-download"></i> @lang('accounting::lang.export_to_excel')
</a>
