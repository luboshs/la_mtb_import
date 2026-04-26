{*
 * NOTICE OF LICENSE
 *
 * This source file is subject to the Academic Free License (AFL 3.0)
 * @author    luboshs
 * @copyright since 2026 luboshs
 *}

<div class="panel">
  <div class="panel-heading">
    <i class="icon-sitemap"></i> {l s='OSC – Category Map' mod='mtbmodelimporter'}
  </div>
  <div class="panel-body">
    <p class="text-muted">
      {l s='Map each osCommerce category to a PrestaShop category. Enable "Ignore" to exclude a category (products in it will not be placed in PS via that category). Unmapped categories without Ignore will use the fallback category.' mod='mtbmodelimporter'}
    </p>

    {if $mappings}
    <form method="post" action="{$categoryMapUrl|escape:'htmlall':'UTF-8'}">
      <input type="hidden" name="token" value="{$token|escape:'htmlall':'UTF-8'}">
      <table class="table table-bordered table-hover">
        <thead>
          <tr>
            <th>{l s='OSC Category ID' mod='mtbmodelimporter'}</th>
            <th>{l s='OSC Category Name' mod='mtbmodelimporter'}</th>
            <th>{l s='PS Category' mod='mtbmodelimporter'}</th>
            <th>{l s='Ignore Binding' mod='mtbmodelimporter'}</th>
          </tr>
        </thead>
        <tbody>
          {foreach $mappings as $k => $row}
          <tr>
            <td>
              {$row.osc_categories_id|intval}
              <input type="hidden" name="osc_cat_id[]" value="{$row.osc_categories_id|intval}">
            </td>
            <td>{$row.osc_category_name|escape:'htmlall':'UTF-8'}</td>
            <td>
              <select name="ps_cat_id[]" class="form-control input-sm">
                <option value="0">{l s='-- not mapped (use fallback) --' mod='mtbmodelimporter'}</option>
                {foreach $psCategories as $cat}
                <option value="{$cat.id_category|intval}"
                  {if $row.ps_id_category == $cat.id_category}selected="selected"{/if}>
                  [{$cat.id_category|intval}] {$cat.name|escape:'htmlall':'UTF-8'}
                </option>
                {/foreach}
              </select>
            </td>
            <td class="text-center">
              <input type="checkbox" name="ignore_binding[{$k}]" value="1"
                {if $row.ignore_binding}checked="checked"{/if}>
            </td>
          </tr>
          {/foreach}
        </tbody>
      </table>
      <button type="submit" name="submitCategoryMap" class="btn btn-primary">
        <i class="icon-save"></i> {l s='Save Category Mappings' mod='mtbmodelimporter'}
      </button>
    </form>
    {else}
    <p>{l s='No categories found. Upload a categories CSV or a products CSV first.' mod='mtbmodelimporter'}</p>
    {/if}
  </div>
</div>
