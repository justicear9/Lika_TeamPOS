@extends('layouts.app')
@section('title', __('approvalworkflow::lang.settings'))

@section('content')
    <section class="content-header">
        <h1>@lang('approvalworkflow::lang.settings')</h1>
        <p class="text-muted">@lang('approvalworkflow::lang.settings_intro')</p>
    </section>

    <section class="content">
        {!! Form::open(['url' => action([\Modules\ApprovalWorkflow\Http\Controllers\SettingsController::class, 'update']), 'method' => 'put']) !!}
        @component('components.widget', ['class' => 'box-solid'])
            @foreach($types as $type => $label)
                @php
                    $rule = $rules[$type] ?? null;
                @endphp
                <h4>@lang($label)</h4>
                <div class="row">
                    <div class="col-sm-12">
                        <div class="checkbox">
                            <label>
                                {!! Form::checkbox("enabled[{$type}]", 1, (bool) ($rule && $rule->is_enabled), ['class' => 'input-icheck']) !!}
                                @lang('approvalworkflow::lang.enable_type')
                            </label>
                        </div>
                    </div>
                </div>
                <div class="row">
                    <div class="col-sm-8">
                        <div class="form-group">
                            {!! Form::label("approvers[{$type}][]", __('approvalworkflow::lang.approvers')) !!}
                            {!! Form::select(
                                "approvers[{$type}][]",
                                $users,
                                $rule ? $rule->approvers->pluck('id')->all() : [],
                                ['class' => 'form-control select2', 'multiple' => 'multiple', 'id' => 'awf_approvers_'.$type]
                            ) !!}
                        </div>
                    </div>
                </div>
                <hr>
            @endforeach
            <div class="text-center">
                <button type="submit" class="btn btn-primary">@lang('messages.update')</button>
            </div>
        @endcomponent
        {!! Form::close() !!}
    </section>
@endsection
