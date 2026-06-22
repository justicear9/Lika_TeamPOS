<table style="width:100%; color:#000;">
	<thead>
		<tr>
			<td>
				<p class="text-center" style="font-size:22px; font-weight:700; letter-spacing:0.5px; margin:0 0 4px 0; text-transform:uppercase;">
					@lang('lang_v1.delivery_note')
				</p>
				@if(!empty($receipt_details->business_name))
					<p class="text-center" style="font-size:18px; font-weight:700; margin:0 0 16px 0;">
						{{ $receipt_details->business_name }}
					</p>
				@endif
			</td>
		</tr>
	</thead>
	<tbody>
		<tr>
			<td>

<div class="row invoice-info" style="margin-bottom:12px;">
	<div class="col-md-5 invoice-col width-50">
		@if(!empty($receipt_details->logo))
			<img style="max-height:90px; width:auto; margin-bottom:8px;" src="{{ $receipt_details->logo }}" class="img" alt="">
		@endif
		@if(!empty($receipt_details->location_name))
			<p style="margin:0 0 4px 0;"><strong>@lang('lang_v1.waybill_delivery_site'):</strong> {{ $receipt_details->location_name }}</p>
		@endif
		@if(!empty($receipt_details->address))
			<p style="margin:0;">{!! $receipt_details->address !!}</p>
		@endif
	</div>

	<div class="col-md-7 invoice-col width-50">
		@if(!empty($receipt_details->customer_label))
			<p style="margin:0 0 4px 0;"><strong>{{ $receipt_details->customer_label }}</strong></p>
		@endif
		@if(!empty($receipt_details->customer_info))
			<p style="margin:0 0 4px 0;">{!! $receipt_details->customer_info !!}</p>
		@endif
		@if(!empty($receipt_details->client_id_label) && !empty($receipt_details->client_id))
			<p style="margin:0 0 4px 0;"><strong>{{ $receipt_details->client_id_label }}</strong> {{ $receipt_details->client_id }}</p>
		@endif
		@if(!empty($receipt_details->shipping_address))
			<p style="margin:0 0 4px 0;"><strong>@lang('lang_v1.shipping_address'):</strong> {!! $receipt_details->shipping_address !!}</p>
		@endif
		@if(!empty($receipt_details->sales_person_label) && !empty($receipt_details->sales_person))
			<p style="margin:0;"><strong>{{ $receipt_details->sales_person_label }}</strong> {{ $receipt_details->sales_person }}</p>
		@endif
	</div>
</div>

<div class="row invoice-info" style="margin-bottom:16px;">
	<div class="col-xs-12">
		<table style="width:100%; border-collapse:collapse; font-size:14px;">
			<tr>
				@if(!empty($receipt_details->invoice_date))
					<td style="padding:4px 8px 4px 0; width:33%;">
						<strong>@lang('lang_v1.date'):</strong> {{ $receipt_details->invoice_date }}
					</td>
				@endif
				@if(!empty($receipt_details->invoice_no))
					<td style="padding:4px 8px; width:33%;">
						<strong>@lang('lang_v1.waybill_delivery_no'):</strong> {{ $receipt_details->invoice_no }}
					</td>
				@endif
				@if(!empty($receipt_details->sale_orders_invoice_no))
					<td style="padding:4px 0 4px 8px; width:34%;">
						<strong>@lang('lang_v1.waybill_sales_order_no'):</strong> {{ $receipt_details->sale_orders_invoice_no }}
					</td>
				@endif
			</tr>
		</table>
	</div>
</div>

<div class="row">
	<div class="col-xs-12">
		<table class="table table-bordered" style="width:100%; border-collapse:collapse; font-size:13px;">
			<thead>
				<tr style="background-color:#d9d9d9 !important; color:#000 !important;">
					<th style="background-color:#d9d9d9 !important; padding:8px 6px; text-align:left; width:12%;">@lang('lang_v1.waybill_product_id')</th>
					<th style="background-color:#d9d9d9 !important; padding:8px 6px; text-align:left; width:34%;">@lang('lang_v1.waybill_product_name')</th>
					<th style="background-color:#d9d9d9 !important; padding:8px 6px; text-align:left; width:14%;">@lang('lang_v1.waybill_lot_no')</th>
					<th style="background-color:#d9d9d9 !important; padding:8px 6px; text-align:left; width:14%;">@lang('lang_v1.waybill_expiry')</th>
					<th style="background-color:#d9d9d9 !important; padding:8px 6px; text-align:right; width:12%;">@lang('lang_v1.waybill_quantity')</th>
					<th style="background-color:#d9d9d9 !important; padding:8px 6px; text-align:left; width:14%;">@lang('lang_v1.waybill_unit_of_measure')</th>
				</tr>
			</thead>
			<tbody>
				@foreach($receipt_details->lines as $line)
					@php
						$product_name = trim(implode(' ', array_filter([
							$line['name'] ?? '',
							$line['product_variation'] ?? '',
							($line['variation'] ?? '') !== 'DUMMY' ? ($line['variation'] ?? '') : '',
						])));
					@endphp
					<tr>
						<td style="padding:8px 6px; vertical-align:top;">{{ $line['sub_sku'] ?? '' }}</td>
						<td style="padding:8px 6px; vertical-align:top; word-break:break-word;">
							{{ $product_name }}
							@if(!empty($line['sell_line_note']))
								<br><small>({!! $line['sell_line_note'] !!})</small>
							@endif
						</td>
						<td style="padding:8px 6px; vertical-align:top;">{{ $line['lot_number'] ?? '' }}</td>
						<td style="padding:8px 6px; vertical-align:top;">{{ $line['product_expiry'] ?? '' }}</td>
						<td style="padding:8px 6px; vertical-align:top; text-align:right;">{{ $line['quantity'] ?? '' }}</td>
						<td style="padding:8px 6px; vertical-align:top;">{{ $line['units'] ?? '' }}</td>
					</tr>
					@if(!empty($line['modifiers']))
						@foreach($line['modifiers'] as $modifier)
							@php
								$modifier_name = trim(implode(' ', array_filter([
									$modifier['name'] ?? '',
									($modifier['variation'] ?? '') !== 'DUMMY' ? ($modifier['variation'] ?? '') : '',
								])));
							@endphp
							<tr>
								<td style="padding:8px 6px; vertical-align:top;">{{ $modifier['sub_sku'] ?? '' }}</td>
								<td style="padding:8px 6px; vertical-align:top; word-break:break-word;">
									{{ $modifier_name }}
									@if(!empty($modifier['sell_line_note']))
										<br><small>({!! $modifier['sell_line_note'] !!})</small>
									@endif
								</td>
								<td style="padding:8px 6px; vertical-align:top;"></td>
								<td style="padding:8px 6px; vertical-align:top;"></td>
								<td style="padding:8px 6px; vertical-align:top; text-align:right;">{{ $modifier['quantity'] ?? '' }}</td>
								<td style="padding:8px 6px; vertical-align:top;">{{ $modifier['units'] ?? '' }}</td>
							</tr>
						@endforeach
					@endif
				@endforeach
			</tbody>
		</table>
	</div>
</div>

<div class="row" style="page-break-inside:avoid !important; margin-top:28px;">
	<div class="col-xs-12">
		<table style="width:100%; border-collapse:collapse;">
			<tr>
				<td style="width:47%; vertical-align:top;">
					@include('sale_pos.receipts.partials.waybill_acknowledgement_box')
				</td>
				<td style="width:6%; text-align:center; vertical-align:middle;">
					<span style="display:inline-block; writing-mode:vertical-rl; text-orientation:mixed; transform:rotate(180deg); font-size:11px; font-weight:600; color:#9e9e9e; letter-spacing:3px;">
						@lang('lang_v1.waybill_stamp')
					</span>
				</td>
				<td style="width:47%; vertical-align:top;">
					@include('sale_pos.receipts.partials.waybill_acknowledgement_box')
				</td>
			</tr>
		</table>
	</div>
</div>

@if($receipt_details->show_barcode)
<br>
<div class="row">
	<div class="col-xs-12">
		<img class="center-block" src="data:image/png;base64,{{ DNS1D::getBarcodePNG($receipt_details->invoice_no, 'C128', 2, 30, [39, 48, 54], true) }}" alt="">
	</div>
</div>
@endif

			</td>
		</tr>
	</tbody>
</table>
