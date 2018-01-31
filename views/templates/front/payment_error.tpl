{*
* 2007-2018 PrestaShop
*
* NOTICE OF LICENSE
*
* This source file is subject to the Academic Free License (AFL 3.0)
* that is bundled with this package in the file LICENSE.txt.
* It is also available through the world-wide-web at this URL:
* http://opensource.org/licenses/afl-3.0.php
* If you did not receive a copy of the license and are unable to
* obtain it through the world-wide-web, please send an email
* to license@prestashop.com so we can send you a copy immediately.
*
* DISCLAIMER
*
* Do not edit or add to this file if you wish to upgrade PrestaShop to newer
* versions in the future. If you wish to customize PrestaShop for your
* needs please refer to http://www.prestashop.com for more information.
*
*  @author    PrestaShop SA <contact@prestashop.com>
*  @copyright 2007-2018 PrestaShop SA
*  @license   http://opensource.org/licenses/afl-3.0.php  Academic Free License (AFL 3.0)
*  International Registered Trademark & Property of PrestaShop SA
*}

<!-- Header part menu -->
<head>
    {block name='head'}
        {include file='_partials/head.tpl'}
    {/block}
</head>

<body>
{hook h='displayAfterBodyOpeningTag'}
<main>
    <!-- Menu part-->
    <header id="header">
        {block name='header'}
            {include file='_partials/header.tpl'}
        {/block}
    </header>

    <!-- Header part ends -->

    <section id="wrapper">
        <div class="container">

            <section id="main">
                <section id="content" class="page-content card card-block">
                    {include file='_partials/breadcrumb.tpl'}
                    <h2>{l s='Error in Paypal' mod='paypal'}</h2>

                    <div class="table-responsive-row clearfix">
                        <p>
                            {$error_paypal}
                        </p>
                    </div>



                </section>
            </section>
        </div>
    </section>
    <!-- Footer starts -->

    <footer id="footer">
        {block name="footer"}
            {include file="_partials/footer.tpl"}
        {/block}
    </footer>
    <!-- Footer Ends -->
    {block name='javascript_bottom'}
        {include file="_partials/javascript.tpl" javascript=$javascript.bottom}
    {/block}
    {hook h='displayBeforeBodyClosingTag'}
</main>

</body>

