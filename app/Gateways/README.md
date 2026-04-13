# Gateways de pagamento – estrutura modular

Cada gateway fica em sua própria pasta (ex: `Spacepag/`, `Efi/`) para facilitar manutenção.

## Estrutura

- `Contracts/GatewayDriver.php` – interface que todo driver implementa.
- `GatewayRegistry.php` – registro de gateways (config + plugins).
- `Spacepag/SpacepagDriver.php` – driver do gateway Spacepag (PIX).
- `Efi/EfiDriver.php` – driver Efí (PIX; boleto/cartão/assinaturas preparados para fase futura). Requer certificado P12 (upload nas configurações) e suporta sandbox/produção.

## Como adicionar um novo gateway (core)

1. Crie a pasta `App\Gateways\<Nome>\` (ex: `Sapcepag\`).
2. Crie o driver implementando `Contracts\GatewayDriver` (ex: `SapcepagDriver.php`).
3. Registre em `config/gateways.php` em `gateways.<slug>` com `driver` apontando para a classe do driver.
4. Se o gateway tiver webhook, crie o controller em `App\Http\Controllers\Webhooks\<Nome>WebhookController.php` e registre a rota em `routes/web.php`.

O `PaymentService` usa o registro e o driver por slug; a ordem por método (PIX, cartão, boleto) vem da configuração do produto ou de `gateway_order` nas configurações.

## Registrar gateway via plugin

É possível adicionar um gateway **sem alterar o código fonte da plataforma**, via plugin.

1. **Estrutura do plugin**  
   Pasta em `plugins/<slug>/` com `plugin.json` e `bootstrap.php`. O plugin deve estar **habilitado** (aba Integrações → Plugins).

2. **Registro no bootstrap**  
   No `bootstrap.php`, chame `GatewayRegistry::register()` com o array do gateway:

   ```php
   return function ($app, \Illuminate\Contracts\Events\Dispatcher $events): void {
       \App\Gateways\GatewayRegistry::register([
           'slug'        => 'meu-gateway',
           'name'        => 'Meu Gateway',
           'image'       => 'images/gateways/meu-gateway.png',
           'methods'     => ['pix', 'card'],
           'scope'       => 'national',
           'country'     => 'br',
           'country_name'=> 'Brasil',
           'signup_url'  => 'https://...',
           'driver'      => \MeuPlugin\MeuGatewayDriver::class,
           'credential_keys' => [
               ['key' => 'api_key', 'label' => 'API Key', 'type' => 'password'],
               ['key' => 'sandbox', 'label' => 'Sandbox', 'type' => 'boolean'],
           ],
           'webhook_handler' => \MeuPlugin\MeuGatewayWebhookHandler::class,
       ]);
   };
   ```

   O `driver` deve implementar `App\Gateways\Contracts\GatewayDriver` (testConnection, createPixPayment, createCardPayment, createBoletoPayment, getTransactionStatus conforme os métodos suportados).

3. **Webhook**  
   A plataforma expõe uma rota genérica: `POST /webhooks/gateways/{slug}`. Se na definição do gateway houver `webhook_handler` (classe ou callable), o request é delegado a ele. O handler deve ter um método `handle(Request $request, string $slug)` (ou ser invocável) e retornar `Response` ou `JsonResponse`. A URL de webhook a configurar no provedor é: `https://seu-dominio.com/webhooks/gateways/meu-gateway`.

4. **Ordem e listagem**  
   Gateways registrados por plugin passam a aparecer na aba **Integrações → Gateways** junto com os demais. A ordem de tentativa (redundância) inclui automaticamente os slugs dos plugins por método; o tenant pode ajustar em Configurações se necessário.

5. **Checkout**  
   No checkout, gateways de plugin usam o componente genérico por método (ex.: cartão = formulário manual estilo Efí/Asaas). Para UI específica (ex.: tokenização no front), seria necessário estender o registry de componentes no frontend.

6. **Chaves no checkout (opcional)**  
   Se o gateway precisar de chaves públicas (ou outras) no frontend, use na definição `checkout_payload_keys` com os nomes das chaves nas credenciais (ex.: `['publishable_key']`). O backend injeta em `card_gateway_keys[slug]` no payload do checkout; o componente recebe a prop `cardGatewayKeys` (objeto `{ [slug]: { [key]: value } }`).

7. **Imagem do gateway a partir do plugin**  
   Para usar uma imagem própria (logo do gateway) servida pelo plugin, use `'image' => 'plugin:{slug}/{caminho}'` e coloque o arquivo em `plugins/{slug}/assets/{caminho}`. Ex.: `'image' => 'plugin:meu-gateway/logo.png'` com o arquivo em `plugins/meu-gateway/assets/logo.png`. A plataforma expõe a rota `GET /plugins/{slug}/assets/{path}`; a URL da imagem é resolvida automaticamente na listagem de gateways (Integrações e Configurações).
