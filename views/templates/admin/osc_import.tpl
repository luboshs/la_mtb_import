{*
 * NOTICE OF LICENSE
 *
 * This source file is subject to the Academic Free License (AFL 3.0)
 * @author    luboshs
 * @copyright since 2026 luboshs
 *}

<div class="panel">
  <div class="panel-heading">
    <i class="icon-upload"></i> {l s='OSC Import – Staging' mod='mtbmodelimporter'}
  </div>
  <div class="panel-body">

    {* Statistics *}
    {if isset($stats.products) && $stats.products}
    <div class="row">
      <div class="col-md-6">
        <h4>{l s='Products Staging' mod='mtbmodelimporter'}</h4>
        <table class="table table-bordered table-sm">
          <tr><td>{l s='Total' mod='mtbmodelimporter'}</td><td>{$stats.products.total|intval}</td></tr>
          <tr><td>{l s='Pending' mod='mtbmodelimporter'}</td><td>{$stats.products.pending|intval}</td></tr>
          <tr><td>{l s='Imported' mod='mtbmodelimporter'}</td><td>{$stats.products.imported|intval}</td></tr>
          <tr><td>{l s='Skipped' mod='mtbmodelimporter'}</td><td>{$stats.products.skipped|intval}</td></tr>
        </table>
      </div>
      <div class="col-md-6">
        <h4>{l s='Specials Staging' mod='mtbmodelimporter'}</h4>
        <table class="table table-bordered table-sm">
          <tr><td>{l s='Total' mod='mtbmodelimporter'}</td><td>{$stats.specials.total|intval}</td></tr>
          <tr><td>{l s='Pending' mod='mtbmodelimporter'}</td><td>{$stats.specials.pending|intval}</td></tr>
          <tr><td>{l s='Imported' mod='mtbmodelimporter'}</td><td>{$stats.specials.imported|intval}</td></tr>
          <tr><td>{l s='Skipped' mod='mtbmodelimporter'}</td><td>{$stats.specials.skipped|intval}</td></tr>
        </table>
      </div>
    </div>
    {/if}

    <hr>

    {* Upload forms *}
    <div class="row">
      <div class="col-md-4">
        <h4>{l s='1. Upload Products CSV' mod='mtbmodelimporter'}</h4>
        <p class="text-muted small">
          {l s='Required columns: products_id, products_name, products_price, products_status. Only rows with products_status=1 are staged.' mod='mtbmodelimporter'}
        </p>
        <form method="post" action="{$oscImportUrl|escape:'htmlall':'UTF-8'}"
              enctype="multipart/form-data">
          <input type="hidden" name="token" value="{$token|escape:'htmlall':'UTF-8'}">
          <div class="input-group">
            <input type="file" name="osc_products_csv" accept=".csv" required>
          </div>
          <button type="submit" name="submitOscProductsCsv" class="btn btn-default btn-sm mt-2">
            <i class="icon-upload"></i> {l s='Upload & Stage' mod='mtbmodelimporter'}
          </button>
        </form>
      </div>
      <div class="col-md-4">
        <h4>{l s='2. Upload Specials CSV' mod='mtbmodelimporter'}</h4>
        <p class="text-muted small">
          {l s='Required columns: specials_id, products_id, specials_new_products_price, status. Only rows with status=1 are staged.' mod='mtbmodelimporter'}
        </p>
        <form method="post" action="{$oscImportUrl|escape:'htmlall':'UTF-8'}"
              enctype="multipart/form-data">
          <input type="hidden" name="token" value="{$token|escape:'htmlall':'UTF-8'}">
          <div class="input-group">
            <input type="file" name="osc_specials_csv" accept=".csv" required>
          </div>
          <button type="submit" name="submitOscSpecialsCsv" class="btn btn-default btn-sm mt-2">
            <i class="icon-upload"></i> {l s='Upload & Stage' mod='mtbmodelimporter'}
          </button>
        </form>
      </div>
      <div class="col-md-4">
        <h4>{l s='3. Upload Categories CSV' mod='mtbmodelimporter'}</h4>
        <p class="text-muted small">
          {l s='Required columns: categories_id, categories_name. Populates the Category Map table.' mod='mtbmodelimporter'}
        </p>
        <form method="post" action="{$oscImportUrl|escape:'htmlall':'UTF-8'}"
              enctype="multipart/form-data">
          <input type="hidden" name="token" value="{$token|escape:'htmlall':'UTF-8'}">
          <div class="input-group">
            <input type="file" name="osc_categories_csv" accept=".csv" required>
          </div>
          <button type="submit" name="submitOscCategoriesCsv" class="btn btn-default btn-sm mt-2">
            <i class="icon-upload"></i> {l s='Upload & Stage' mod='mtbmodelimporter'}
          </button>
        </form>
      </div>
    </div>

    <hr>

    {* Batch import actions *}
    <div class="row">
      <div class="col-md-6">
        <h4>{l s='4. Batch Import Products' mod='mtbmodelimporter'}</h4>
        <p class="text-muted small">
          {l s='Processes the next batch of pending staged products into PrestaShop. Run repeatedly until all pending records are 0.' mod='mtbmodelimporter'}
        </p>
        <form method="post" action="{$oscImportUrl|escape:'htmlall':'UTF-8'}">
          <input type="hidden" name="token" value="{$token|escape:'htmlall':'UTF-8'}">
          <button type="submit" name="submitOscBatchImport" class="btn btn-primary">
            <i class="icon-cogs"></i> {l s='Run Batch Import' mod='mtbmodelimporter'}
          </button>
        </form>
      </div>
      <div class="col-md-6">
        <h4>{l s='5. Batch Import Specials' mod='mtbmodelimporter'}</h4>
        <p class="text-muted small">
          {l s='Imports pending specials as SpecificPrice records. Run after products batch is complete.' mod='mtbmodelimporter'}
        </p>
        <form method="post" action="{$oscImportUrl|escape:'htmlall':'UTF-8'}">
          <input type="hidden" name="token" value="{$token|escape:'htmlall':'UTF-8'}">
          <button type="submit" name="submitOscBatchSpecials" class="btn btn-primary">
            <i class="icon-cogs"></i> {l s='Run Specials Batch' mod='mtbmodelimporter'}
          </button>
        </form>
      </div>
    </div>

  </div>
</div>

{* Settings form *}
{$settingsForm}
