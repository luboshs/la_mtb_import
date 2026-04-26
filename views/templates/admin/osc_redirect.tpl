{*
 * NOTICE OF LICENSE
 *
 * This source file is subject to the Academic Free License (AFL 3.0)
 * @author    luboshs
 * @copyright since 2026 luboshs
 *}

<div class="panel">
  <div class="panel-heading">
    <i class="icon-random"></i> {l s='OSC – Redirects' mod='mtbmodelimporter'}
  </div>
  <div class="panel-body">
    <p class="text-muted">
      {l s='These records map old osCommerce URLs to their new PrestaShop equivalents. Use them to configure 301 redirects in .htaccess or a front controller.' mod='mtbmodelimporter'}
    </p>

    <form method="get" action="{$redirectUrl|escape:'htmlall':'UTF-8'}" class="form-inline mb-3">
      <input type="hidden" name="token" value="{$token|escape:'htmlall':'UTF-8'}">
      <select name="filter_type" class="form-control input-sm mr-2">
        <option value="">{l s='All types' mod='mtbmodelimporter'}</option>
        <option value="product" {if $filterType == 'product'}selected="selected"{/if}>
          {l s='Product' mod='mtbmodelimporter'}
        </option>
        <option value="category" {if $filterType == 'category'}selected="selected"{/if}>
          {l s='Category' mod='mtbmodelimporter'}
        </option>
      </select>
      <button type="submit" class="btn btn-default btn-sm">
        <i class="icon-search"></i> {l s='Filter' mod='mtbmodelimporter'}
      </button>
    </form>

    {if $redirects}
    <table class="table table-bordered table-hover table-sm">
      <thead>
        <tr>
          <th>{l s='Type' mod='mtbmodelimporter'}</th>
          <th>{l s='OSC ID' mod='mtbmodelimporter'}</th>
          <th>{l s='Old URL (osCommerce)' mod='mtbmodelimporter'}</th>
          <th>{l s='New URL (PrestaShop)' mod='mtbmodelimporter'}</th>
          <th>{l s='Created' mod='mtbmodelimporter'}</th>
        </tr>
      </thead>
      <tbody>
        {foreach $redirects as $row}
        <tr>
          <td>
            <span class="badge badge-{if $row.type == 'product'}info{else}warning{/if}">
              {$row.type|escape:'htmlall':'UTF-8'}
            </span>
          </td>
          <td>{$row.osc_id|intval}</td>
          <td><code>{$row.osc_url|escape:'htmlall':'UTF-8'}</code></td>
          <td><code>{$row.ps_url|escape:'htmlall':'UTF-8'}</code></td>
          <td>{$row.created_at|escape:'htmlall':'UTF-8'}</td>
        </tr>
        {/foreach}
      </tbody>
    </table>
    <p class="text-muted small">
      {l s='Total:' mod='mtbmodelimporter'} {$redirects|count}
    </p>
    {else}
    <p>{l s='No redirect records found. Run the batch product import first.' mod='mtbmodelimporter'}</p>
    {/if}
  </div>
</div>
