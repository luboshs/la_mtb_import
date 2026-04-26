{**
 * NOTICE OF LICENSE
 *
 * This source file is subject to the Academic Free License (AFL 3.0)
 * that is bundled with this package in the file LICENSE.
 * It is also available through the world-wide-web at this URL:
 * https://opensource.org/licenses/AFL-3.0
 *
 * @author    luboshs
 * @copyright since 2026 luboshs
 * @license   https://opensource.org/licenses/AFL-3.0 Academic Free License (AFL 3.0)
 *}

<div class="panel">
    <div class="panel-heading">
        <i class="icon-list"></i>
        {l s='Product Suggestions' mod='mtbmodelimporter'}
    </div>
    <div class="panel-body">
        <form method="get" action="{$productsUrl|escape:'htmlall':'UTF-8'}">
            <input type="hidden" name="token" value="{$token|escape:'htmlall':'UTF-8'}">
            <div class="row">
                <div class="col-lg-3">
                    <select name="filter_scale" class="form-control">
                        <option value="">{l s='All scales' mod='mtbmodelimporter'}</option>
                        {foreach $scales as $scale}
                        <option value="{$scale|escape:'htmlall':'UTF-8'}" {if $filterScale eq $scale}selected="selected"{/if}>
                            {$scale|escape:'htmlall':'UTF-8'}
                        </option>
                        {/foreach}
                    </select>
                </div>
                <div class="col-lg-3">
                    <select name="filter_status" class="form-control">
                        <option value="">{l s='All statuses' mod='mtbmodelimporter'}</option>
                        <option value="new" {if $filterStatus eq 'new'}selected="selected"{/if}>{l s='New' mod='mtbmodelimporter'}</option>
                        <option value="changed" {if $filterStatus eq 'changed'}selected="selected"{/if}>{l s='Changed' mod='mtbmodelimporter'}</option>
                        <option value="ready" {if $filterStatus eq 'ready'}selected="selected"{/if}>{l s='Ready' mod='mtbmodelimporter'}</option>
                        <option value="imported" {if $filterStatus eq 'imported'}selected="selected"{/if}>{l s='Imported' mod='mtbmodelimporter'}</option>
                    </select>
                </div>
                <div class="col-lg-2">
                    <button type="submit" class="btn btn-default">
                        <i class="icon-search"></i> {l s='Filter' mod='mtbmodelimporter'}
                    </button>
                </div>
            </div>
        </form>
    </div>
</div>

{if $products|@count > 0}
<div class="panel">
    <div class="panel-body">
        <table class="table table-bordered table-striped">
            <thead>
                <tr>
                    <th>{l s='ID' mod='mtbmodelimporter'}</th>
                    <th>{l s='Scale' mod='mtbmodelimporter'}</th>
                    <th>{l s='Raw Name' mod='mtbmodelimporter'}</th>
                    <th>{l s='Admin Name' mod='mtbmodelimporter'}</th>
                    <th>{l s='Price' mod='mtbmodelimporter'}</th>
                    <th>{l s='EAN' mod='mtbmodelimporter'}</th>
                    <th>{l s='Status' mod='mtbmodelimporter'}</th>
                    <th>{l s='Actions' mod='mtbmodelimporter'}</th>
                </tr>
            </thead>
            <tbody>
                {foreach $products as $product}
                <tr>
                    <td>{$product.id|intval}</td>
                    <td>{$product.scale|escape:'htmlall':'UTF-8'}</td>
                    <td>{$product.supplier_raw_name|escape:'htmlall':'UTF-8'}</td>
                    <td>
                        <form method="post" action="{$productsUrl|escape:'htmlall':'UTF-8'}" class="form-inline">
                            <input type="hidden" name="token" value="{$token|escape:'htmlall':'UTF-8'}">
                            <input type="hidden" name="id_import" value="{$product.id|intval}">
                            <div class="input-group input-group-sm">
                                <input type="text" name="admin_name"
                                    class="form-control"
                                    value="{$product.admin_name|default:''|escape:'htmlall':'UTF-8'}"
                                    placeholder="{$product.generated_name|default:''|escape:'htmlall':'UTF-8'}">
                                <span class="input-group-btn">
                                    <button type="submit" name="submitEditName" value="1" class="btn btn-default btn-sm">
                                        <i class="icon-save"></i>
                                    </button>
                                </span>
                            </div>
                        </form>
                    </td>
                    <td>{$product.dealer_price|default:''|escape:'htmlall':'UTF-8'}</td>
                    <td>{$product.ean_normalized|default:''|escape:'htmlall':'UTF-8'}</td>
                    <td>
                        <span class="label label-{if $product.status eq 'new'}default{elseif $product.status eq 'changed'}warning{elseif $product.status eq 'ready'}info{else}success{/if}">
                            {$product.status|escape:'htmlall':'UTF-8'}
                        </span>
                    </td>
                    <td>
                        {if $product.status neq 'imported'}
                        <form method="post" action="{$productsUrl|escape:'htmlall':'UTF-8'}">
                            <input type="hidden" name="token" value="{$token|escape:'htmlall':'UTF-8'}">
                            <input type="hidden" name="id_import" value="{$product.id|intval}">
                            <button type="submit" name="submitApprove" value="1" class="btn btn-info btn-sm">
                                <i class="icon-check"></i> {l s='Mark Ready' mod='mtbmodelimporter'}
                            </button>
                        </form>
                        {if $product.status eq 'ready'}
                        <form method="post" action="{$productsUrl|escape:'htmlall':'UTF-8'}" style="margin-top:5px;">
                            <input type="hidden" name="token" value="{$token|escape:'htmlall':'UTF-8'}">
                            <input type="hidden" name="id_import" value="{$product.id|intval}">
                            <div class="form-group">
                                <select name="id_category" class="form-control input-sm" required>
                                    <option value="">{l s='Select category' mod='mtbmodelimporter'}</option>
                                    {foreach $categories as $category}
                                    <option value="{$category.id_category|intval}">
                                        {$category.name|escape:'htmlall':'UTF-8'}
                                    </option>
                                    {/foreach}
                                </select>
                                <select name="id_manufacturer" class="form-control input-sm">
                                    <option value="0">{l s='No brand' mod='mtbmodelimporter'}</option>
                                    {foreach $manufacturers as $manufacturer}
                                    <option value="{$manufacturer.id_manufacturer|intval}">
                                        {$manufacturer.name|escape:'htmlall':'UTF-8'}
                                    </option>
                                    {/foreach}
                                </select>
                            </div>
                            <button type="submit" name="submitImport" value="1" class="btn btn-success btn-sm">
                                <i class="icon-upload"></i> {l s='Import to Shop' mod='mtbmodelimporter'}
                            </button>
                        </form>
                        {/if}
                        {/if}
                    </td>
                </tr>
                {/foreach}
            </tbody>
        </table>
    </div>
</div>
{else}
<div class="panel">
    <div class="panel-body">
        <p class="text-muted">{l s='No product suggestions found.' mod='mtbmodelimporter'}</p>
    </div>
</div>
{/if}
