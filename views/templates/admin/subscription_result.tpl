<div class="panel">
  <div class="panel-heading">{l s='Subscription Action' mod='paymentpayee'}</div>
  <div class="panel-body">
    {if isset($confirmations)}
      {foreach $confirmations as $msg}
        <div class="alert alert-success">{$msg}</div>
      {/foreach}
    {/if}
    <a href="{$link->getAdminLink('AdminOrders', true, [], [
  'id_order' => (int)$smarty.get.id_order,
  'vieworder' => 1
])}"
       class="btn btn-default">
      {l s='Back to order' mod='paymentpayee'}
    </a>
  </div>
</div>