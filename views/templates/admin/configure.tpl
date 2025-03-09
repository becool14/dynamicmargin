<div class="panel">
    <div class="panel-heading">
        {l s='Dynamic Margin Configuration' mod='dynamicmargin'}
    </div>
    <form action="{$smarty.server.REQUEST_URI|escape:'htmlall':'UTF-8'}" method="post" class="form-horizontal">
        <div class="form-group">
            <label class="control-label col-lg-3">
                {l s='Global Margin (%)' mod='dynamicmargin'}
            </label>
            <div class="col-lg-9">
                <input type="number" 
                       name="DYNAMICMARGIN_GLOBAL_MARGIN" 
                       value="{$current_margin|escape:'htmlall':'UTF-8'}" 
                       step="0.01"
                       min="0"
                       class="form-control" />
            </div>
        </div>
        <div class="panel-footer">
            <button type="submit" name="submitDynamicmargin" class="btn btn-default pull-right">
                <i class="process-icon-save"></i> {l s='Save' mod='dynamicmargin'}
            </button>
        </div>
    </form>
</div>

<div class="panel">
    <div class="panel-heading">
        {l s='Margin Change History' mod='dynamicmargin'}
    </div>
    <div class="table-responsive">
        <table class="table">
            <thead>
                <tr>
                    <th>{l s='Date' mod='dynamicmargin'}</th>
                    <th>{l s='Previous Value' mod='dynamicmargin'}</th>
                    <th>{l s='New Value' mod='dynamicmargin'}</th>
                    <th>{l s='Changed By' mod='dynamicmargin'}</th>
                </tr>
            </thead>
            <tbody>
                {foreach from=$margin_history item=history}
                    <tr>
                        <td>{$history.date_add|escape:'htmlall':'UTF-8'}</td>
                        <td>{$history.previous_value|escape:'htmlall':'UTF-8'}%</td>
                        <td>{$history.margin_value|escape:'htmlall':'UTF-8'}%</td>
                        <td>{$history.employee_name|escape:'htmlall':'UTF-8'}</td>
                    </tr>
                {/foreach}
            </tbody>
        </table>
    </div>
</div>