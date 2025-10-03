{**
 * Copyright since 2007 PrestaShop SA and Contributors
 * PrestaShop is an International Registered Trademark & Property of PrestaShop SA
 *
 * NOTICE OF LICENSE
 *
 * This source file is subject to the Academic Free License 3.0 (AFL-3.0)
 * that is bundled with this package in the file LICENSE.md.
 * It is also available through the world-wide-web at this URL:
 * https://opensource.org/licenses/AFL-3.0
 * If you did not receive a copy of the license and are unable to
 * obtain it through the world-wide-web, please send an email
 * to license@prestashop.com so we can send you a copy immediately.
 *
 * @author    PrestaShop SA <contact@prestashop.com>
 * @copyright Since 2007 PrestaShop SA and Contributors
 * @license   https://opensource.org/licenses/AFL-3.0 Academic Free License 3.0 (AFL-3.0)
 *}

<section id="{$moduleName}-displayAdminOrderMainBottom">
  <div class="card mt-2">
    <div class="card-header">
      <h3 class="card-header-title">
        <img src="{$moduleLogoSrc}" alt="{$moduleDisplayName}" width="20" height="20">
        {$moduleDisplayName}
      </h3>
    </div>
    <div class="card-body">
      <p>{l s='This order has been paid with %moduleDisplayName%.' mod='paymentpayee' sprintf=['%moduleDisplayName%' => $moduleDisplayName]}</p>
    </div>
  {if $details}
  <div class="card-body">
    <details><summary>Transaction Details ID={$transaction_id} ({$status})</summary><pre>{$details}</pre></details>
  </div>
  {/if}
  <!-- Debug: issubscription={$issubscription} -->
  {if $issubscription}
  <div class="card-body">
    <a href=href="{$link->getAdminLink('AdminSubscription')}&action=testSubscription&id_order={$id_order}"
       class="btn btn-info">
        {l s='Verify Subscription' mod='paymentpayee'}
    </a>
    <a href="{$link->getAdminLink('AdminSubscription')}&action=recurNow&id_order={$id_order}"
       class="btn btn-success">
       {l s='Do Payment Now' mod='paymentpayee'}
    </a>
     <a href="{$link->getAdminLink('AdminSubscription')}&action=cancel&id_order={$id_order}"
       class="btn btn-danger">
       {l s='Cancel Now' mod='paymentpayee'}
    </a>
    </div>
  {/if}

  </div>
   
</section>
