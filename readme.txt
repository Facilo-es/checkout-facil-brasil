=== Checkout Fácil Brasil for WooCommerce ===
Contributors: facilo
Tags: cpf, cnpj, brasil, checkout, woocommerce
Requires at least: 6.6
Tested up to: 7.1
Requires PHP: 7.4
Stable tag: 1.0.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

CPF, CNPJ (including the new alphanumeric CNPJ), número and bairro on the WooCommerce block and classic checkout. Reads Brazilian Market data.

== Description ==

Sell to Brazil without losing orders at the last step. Checkout Fácil Brasil adds the fields Brazilian orders need, "CPF", "CNPJ", "número" and "bairro", to both the new block checkout ("checkout em blocos") and the classic checkout. It validates the new alphanumeric CNPJ ("CNPJ alfanumérico") issued since July 2026, so new companies can buy from you. Switching from Brazilian Market on WooCommerce? Your orders, customers and settings are read as they are: nothing to migrate.

= What your shop gets =

* **Fewer abandoned checkouts.** Shoppers choose Pessoa Física or Pessoa Jurídica and only see the fields they need. Masks and clear messages help them type a valid CPF or CNPJ the first time.
* **Brazilian fields on any checkout ("campos brasileiros checkout").** Works on the Checkout block (WooCommerce 8.9 and later) and on the classic shortcode checkout, with the same data on both.
* **Addresses your carrier can deliver to.** "Número e bairro" for billing and shipping, with a "Sem número" box, hidden for other countries.
* **Companies can buy, including new ones.** The CNPJ check accepts the alphanumeric format (letters in the first 12 positions) as well as the classic numeric one. Razão social is required for companies.
* **Payment and NF-e plugins keep working.** Data is saved under the same order and customer keys the old plugin used (`_billing_cpf`, `_billing_cnpj`, `_billing_number`, `_billing_neighborhood` and the rest), on block and classic orders, with HPOS on or off.
* **Everything in one place.** The documents show on the admin order screen, in emails, on the thank-you page, in My Account and in the REST API.
* **Optional extras:** Celular, RG, Inscrição Estadual (with Isento), Data de nascimento and Gênero. Each one can be off, optional or required.
* **Set up in a minute.** A three-step setup with a live preview of your checkout.
* **Privacy (LGPD).** A suggested privacy policy text, plus personal data export and erase for every field. The plugin makes no outside requests.

This plugin collects and validates checkout fields. It does not issue invoices (NF-e) or calculate taxes.

= Checkout Fácil Brasil Pro =

Pro is a separate plugin that adds "endereço pelo CEP" (the address fills in from the CEP), company data from the CNPJ with a status badge on the order, Inscrição Estadual checked for each of the 27 states, sign-up and login with CPF or CNPJ, and a CPF/CNPJ column and search in the orders list. Everything in this free plugin stays free and complete without Pro.

= Support =

Questions are answered in this plugin's support forum on wordpress.org. Each version is supported for 12 months after its release, and we keep it tested with new WordPress and WooCommerce versions. Security reports go to security@facilo.es.

== Installation ==

1. In WordPress, go to Plugins > Add New, search for "Checkout Fácil Brasil" and click Install, then Activate.
2. Open WooCommerce > Checkout Fácil Brasil and follow the three steps: who buys from you, which fields to show, and done.
3. Place a test order on your checkout to see the fields.

If Brazilian Market on WooCommerce is active, its settings are imported and a notice offers to deactivate it. Our fields appear once the old plugin is off.

== Frequently Asked Questions ==

= Does it work with the Checkout block? =

Yes. On the block checkout ("checkout em blocos CPF") the fields are added through WooCommerce's own Additional Checkout Fields API, from WooCommerce 8.9. On WooCommerce 9.9 and later only the CPF or the CNPJ shows, depending on the person type. On older versions both show and only the right one is required. The classic checkout works too.

= I'm moving from Brazilian Market on WooCommerce. Do I lose data? =

No. Checkout Fácil Brasil uses the same order and customer keys, so your old orders and customers show their CPF, CNPJ, número and bairro as they are, with nothing to migrate. Your old settings are imported on activation. While the old plugin is still active, ours adds no fields, so nothing shows twice.

= Does it accept the alphanumeric CNPJ? =

Yes. From July 2026 new CNPJs can have letters in the first 12 positions, such as 12.ABC.345/01DE-35. The plugin checks both the new and the numeric format, and upper-cases letters as the shopper types.

= Do payment and NF-e plugins still find the CPF? =

Yes. The CPF, CNPJ and address data are saved under the keys those plugins already read, such as `_billing_cpf` and `_billing_cnpj`, on both block and classic orders.

= Does it issue invoices? =

No. This plugin collects and validates checkout fields. It does not issue invoices (NF-e) or calculate taxes. Use your NF-e plugin or service for that: it reads the data this plugin saves.

= What does Pro add? =

The address fills in from the CEP ("endereço pelo CEP"), company name and address fill in from the CNPJ with a status badge on the order, Inscrição Estadual is checked for each state, customers can sign up and log in with their CPF or CNPJ, and the orders list gets a CPF/CNPJ column and search. See the Pro tab in the plugin settings.

= Does the free plugin contact outside services? =

No. Checks run in your shop, and no data leaves it.

== Screenshots ==

1. Block checkout, Pessoa Física.
2. Block checkout, Pessoa Jurídica with an alphanumeric CNPJ.
3. Classic checkout.
4. The notice after importing settings from Brazilian Market on WooCommerce.
5. The admin order screen with the customer's documents.
6. The three-step setup.

== Changelog ==

= 1.0.0 =
* First release: CPF, CNPJ (numeric and alphanumeric), Razão social, Número, Bairro, Celular, RG, Inscrição Estadual, Data de nascimento and Gênero on the block and classic checkouts, import from Brazilian Market on WooCommerce, LGPD export and erase.

== Upgrade Notice ==

= 1.0.0 =
First release.
