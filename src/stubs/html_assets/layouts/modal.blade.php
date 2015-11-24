<div class="modal-header">
    <h3 class="modal-title">@yield('title')</h3>
</div>
<div class="modal-body">
    @yield('content')
</div>
<div class="modal-footer">
    <button class="btn btn-primary" type="button" ng-click="ok()">@yield('button')</button>
    <button class="btn btn-warning" type="button" ng-click="cancel()">Cancel</button>
</div>