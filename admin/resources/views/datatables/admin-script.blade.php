{{-- Mounts the table through AdminTable.init() (public/js/admin.js) so it gets the shared toolbar. %1$s = table id, %2$s = options. --}}
$(function(){window.{{ config('datatables-html.namespace', 'LaravelDataTables') }}=window.{{ config('datatables-html.namespace', 'LaravelDataTables') }}||{};window.{{ config('datatables-html.namespace', 'LaravelDataTables') }}["%1$s"]=AdminTable.init("#%1$s",%2$s);});
@foreach ($scripts as $script)
@include($script)
@endforeach
