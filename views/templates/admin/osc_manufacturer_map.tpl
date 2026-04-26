{*
 * NOTICE OF LICENSE
 *
 * This source file is subject to the Academic Free License (AFL 3.0)
 * @author    luboshs
 * @copyright since 2026 luboshs
 *}

<div class="panel">
  <div class="panel-heading">
    <i class="icon-star"></i> {l s='OSC – Brand / Manufacturer Map' mod='mtbmodelimporter'}
  </div>
  <div class="panel-body">
    <p class="text-muted">
      {l s='Map osCommerce manufacturer names to existing PrestaShop manufacturers. Leave unmapped to let the import auto-create a new manufacturer with the same name.' mod='mtbmodelimporter'}
    </p>

    {if $mappings}
    <form method="post" action="{$mfrMapUrl|escape:'htmlall':'UTF-8'}">
      <input type="hidden" name="token" value="{$token|escape:'htmlall':'UTF-8'}">
      <table class="table table-bordered table-hover">
        <thead>
          <tr>
            <th>{l s='OSC Manufacturer Name' mod='mtbmodelimporter'}</th>
            <th>{l s='PS Manufacturer' mod='mtbmodelimporter'}</th>
          </tr>
        </thead>
        <tbody>
          {foreach $mappings as $row}
          <tr>
            <td>
              {$row.osc_manufacturers_name|escape:'htmlall':'UTF-8'}
              <input type="hidden" name="map_id[]" value="{$row.id|intval}">
            </td>
            <td>
              <select name="ps_manufacturer_id[]" class="form-control input-sm">
                <option value="0">{l s='-- auto-create from name --' mod='mtbmodelimporter'}</option>
                {foreach $psManufacturers as $mfr}
                <option value="{$mfr.id_manufacturer|intval}"
                  {if $row.ps_id_manufacturer == $mfr.id_manufacturer}selected="selected"{/if}>
                  {$mfr.name|escape:'htmlall':'UTF-8'}
                </option>
                {/foreach}
              </select>
            </td>
          </tr>
          {/foreach}
        </tbody>
      </table>
      <button type="submit" name="submitManufacturerMap" class="btn btn-primary">
        <i class="icon-save"></i> {l s='Save Manufacturer Mappings' mod='mtbmodelimporter'}
      </button>
    </form>
    {else}
    <p>{l s='No manufacturer names found. Upload a products CSV first.' mod='mtbmodelimporter'}</p>
    {/if}
  </div>
</div>
