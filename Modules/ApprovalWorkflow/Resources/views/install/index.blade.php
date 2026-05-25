@extends('layouts.app')
@section('title', __('approvalworkflow::lang.module_name'))

@section('content')
<section class="content">
    <div class="row">
        <div class="col-md-8 col-md-offset-2">
            <div class="box box-primary">
                <div class="box-header with-border">
                    <h3 class="box-title">{{ __('lang_v1.install') }}: @lang('approvalworkflow::lang.module_name')</h3>
                </div>
                <div class="box-body">
                    <p>@lang('approvalworkflow::lang.install_help')</p>
                    <form method="post" action="{{ $action_url }}">
                        {{ csrf_field() }}
                        <button type="submit" class="btn btn-primary">@lang('lang_v1.install')</button>
                        <a href="{{ action([\App\Http\Controllers\Install\ModulesController::class, 'index']) }}" class="btn btn-default">@lang('messages.cancel')</a>
                    </form>
                </div>
            </div>
        </div>
    </div>
</section>
@endsection
