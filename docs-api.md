API de Pagamentos
Integre pagamentos (PIX, cartão, boleto) na sua plataforma usando os gateways configurados na Getfy. Checkout hospedado ou transparente - você escolhe.

REST
Bearer / X-API-Key
JSON
Base URL
https://pay.getfy.cloud/api/v1
Autenticação: Authorization: Bearer <sua_api_key> ou header X-API-Key.

Ver detalhes da autenticação →
Início rápido
Base URL: Todas as rotas estão sob /api/v1.

Autenticação: Envie a API key no header Authorization: Bearer <sua_api_key> ou X-API-Key: <sua_api_key>.

Resumo dos endpoints
Método	Endpoint	Descrição
POST	/api/v1/checkout/sessions	Criar sessão Checkout Pro (retorna link)
POST	/api/v1/payments/pix	Criar pagamento PIX
POST	/api/v1/payments/card	Criar pagamento com cartão
POST	/api/v1/payments/boleto	Criar pagamento com boleto
GET	/api/v1/payments/{order_id}	Consultar status do pedido
Visão geral
Checkout Pro (hospedado)
Sua plataforma envia os dados do cliente e do valor para a API.
A API devolve um link de checkout que o usuário final abre no navegador.
Na Getfy o usuário só escolhe o método (PIX, boleto) e conclui o pagamento; não preenche nome, e-mail, CPF (já vêm da sessão).
Ideal quando você quer delegar toda a tela de pagamento à Getfy.
Checkout Transparente
Sua plataforma mantém a própria UI (formulário de cartão, exibição de PIX, etc.).
Você chama a API para criar a cobrança e recebe os dados (QR code PIX, link do boleto, resultado do cartão, etc.).
Pode consultar o status do pagamento via GET /api/v1/payments/{order_id}.
Ideal quando o fluxo de compra e a identidade visual ficam no seu site/app.
Quando usar cada um
Cenário	Sugestão
Redirecionar o cliente para uma página de pagamento da Getfy	Checkout Pro
Manter checkout no seu site (iframe, SPA, app)	Checkout Transparente
Apenas processar pagamento (sem produto Getfy)	Ambos suportam amount/currency sem product_id
Envio da API key
Todas as requisições à API devem incluir a API key da aplicação.

Header preferido: Authorization: Bearer <sua_api_key>
Alternativa: X-API-Key: <sua_api_key>
Exemplo:

http
 POST /api/v1/checkout/sessions HTTP/1.1 Host: pay.getfy.cloud Authorization: Bearer getfy_xxxxxxxx_yyyyyyyyyyyyyyyyyyyyyyyyyyyyyyyy Content-Type: application/json 
Obtenção da API key
No painel Getfy, acesse API Pagamentos (ou Aplicações).
Crie uma nova aplicação ou edite uma existente.
Na criação, a API key é exibida uma única vez; copie e guarde em local seguro.
Em edição, use Gerar nova API key se precisar de uma nova (a anterior deixa de funcionar).
Segurança
Nunca exponha a API key em frontend público (JavaScript, apps móveis sem proteção). Use sempre um backend seu para chamar a API.
Em produção, utilize HTTPS em todas as requisições.
A API key é armazenada apenas como hash no servidor; não é possível recuperá-la depois. Se perder, gere uma nova.
O que é uma aplicação
Cada Aplicação representa um cliente da API (ex.: sua loja, seu SaaS). A API atua como roteador de pagamentos: você autentica com uma aplicação (API key), escolhe o modo de checkout e os pagamentos são processados com a ordem e redundância de gateways definidas por aplicação.

Configuração
Na aplicação você configura:

Nome e identificação (slug).
Gateways por método: PIX, cartão, boleto (e opcionalmente PIX automático, cripto), com redundância (ordem de fallback).
Webhook URL (opcional): URL que receberá notificações de pagamento (order.completed, order.pending, order.refunded).
URL de retorno padrão (opcional): usada no Checkout Pro quando a sessão não enviar return_url.
Webhook secret (opcional): usado para assinar o body do webhook (header X-Getfy-Signature). Recomendado em produção.
IPs permitidos (opcional): lista de IPs que podem usar a API key; vazio = todos permitidos.
Ativo: aplicações inativas retornam 403.
A ordem e redundância dos gateways são aplicadas a todos os pagamentos criados por essa aplicação (Checkout Pro e Transparente).

POST
/api/v1/checkout/sessions
Cria uma sessão e retorna a URL para o usuário final concluir o pagamento.

Body (JSON)
Campo	Tipo	Obrigatório	Descrição
customer	objeto	Sim	Dados do cliente
customer.email	string	Sim	E-mail
customer.name	string	Não	Nome (default: email)
customer.cpf	string	Não	CPF
customer.phone	string	Não	Telefone
amount	number	Sim	Valor (ex.: 97.90)
currency	string	Não	BRL, USD ou EUR (default: BRL)
product_id	string (UUID)	Não	ID do produto Getfy; se informado, o pedido fica vinculado e o acesso é concedido ao concluir
product_offer_id	integer	Não	ID da oferta do produto
subscription_plan_id	integer	Não	ID do plano de assinatura
metadata	objeto	Não	Dados livres (ex.: external_id) para uso no webhook
return_url	string	Não	URL final para voltar ao seu site após concluir; se omitida, usa a URL de retorno padrão da aplicação
expires_in	integer	Não	Minutos até expirar a sessão (5–1440; default: 30)
Resposta 201
json
{
  "session_id": "123",
  "checkout_url": "https://pay.getfy.cloud/api-checkout/xxxxxxxx-xxxx-xxxx-xxxx-xxxxxxxxxxxx",
  "expires_at": "2026-03-09T12:30:00.000000Z"
}
checkout_url: link que o usuário final deve abrir. Na página, ele verá valor e método de pagamento (PIX, boleto); não preenche dados de cliente. A sessão expira no horário indicado em expires_at.

Fluxo do usuário final
Sua plataforma redireciona o cliente para checkout_url ou abre em nova aba.
Na Getfy o cliente vê valor, produto (se houver) e escolhe PIX ou boleto.
Após a confirmação do pagamento, a Getfy exibe uma página de confirmação e redireciona o cliente de volta para return_url (ou para a URL de retorno padrão da aplicação).
Dados comuns (customer)
Em todos os endpoints de criação de pagamento, use o objeto customer. Campos opcionais comuns em todos: amount, currency, product_id, product_offer_id, subscription_plan_id, metadata, idempotency_key (ou header Idempotency-Key).

Campo	Tipo	Obrigatório	Descrição
customer	objeto	Sim	Dados do cliente
customer.email	string	Sim	E-mail
customer.name	string	Não	Nome
customer.cpf	string	Não	CPF
customer.phone	string	Não	Telefone
POST
/api/v1/payments/pix
Cria um pedido e uma cobrança PIX. Retorna QR code e copia e cola. Os campos de cliente seguem os dados comuns (customer).

Body (JSON)
Campo	Tipo	Obrigatório	Descrição
customer	objeto	Sim	Ver dados comuns acima
amount	number	Sim	Valor
currency	string	Não	BRL, USD ou EUR (default: BRL)
product_id	string (UUID)	Não	ID do produto Getfy
product_offer_id	integer	Não	ID da oferta
subscription_plan_id	integer	Não	ID do plano de assinatura
metadata	objeto	Não	Dados livres para webhook
idempotency_key	string	Não	Ou header Idempotency-Key (até 128 caracteres)
Resposta 201
json
{ "order_id": 456, "transaction_id": "abc123", "qrcode": "data:image/png;base64,...", "copy_paste": "00020126...", "status": "pending" }
Use qrcode (imagem) ou copy_paste (código PIX) na sua UI. O status pode ser consultado em GET /api/v1/payments/{order_id} ou via webhook.

POST
/api/v1/payments/card
Cria um pedido e processa o pagamento com cartão. Campos de cliente e valor seguem os dados comuns (customer).

Body (JSON)
Campo	Tipo	Obrigatório	Descrição
customer	objeto	Sim	Ver dados comuns acima
amount	number	Sim	Valor
currency	string	Não	Default BRL
product_id, product_offer_id, subscription_plan_id, metadata	—	Não	Opcionais
card	objeto	Sim	Dados do cartão
card.payment_token	string	Sim	Token do cartão (gateway/JS Getfy ou sua tokenização)
card.card_mask	string	Não	Máscara (ex.: **** 1234)
idempotency_key	string	Não	Ou header Idempotency-Key
Resposta 201
json
{ "order_id": 456, "transaction_id": "xyz", "status": "paid", "client_secret": "..." }
status pode ser pending, paid, approved, completed ou outro conforme o gateway. client_secret aparece quando o gateway exige (ex.: 3DS).

POST
/api/v1/payments/boleto
Cria um pedido e gera um boleto. Campos de cliente seguem os dados comuns (customer).

Body (JSON)
Campo	Tipo	Obrigatório	Descrição
customer	objeto	Sim	Ver dados comuns acima
amount	number	Sim	Valor
currency	string	Não	Default BRL
product_id, product_offer_id, subscription_plan_id, metadata	—	Não	Opcionais
idempotency_key	string	Não	Ou header Idempotency-Key
Resposta 201
json
{ "order_id": 456, "transaction_id": "bol_xxx", "barcode": "12345.67890 12345.678901 12345.678901 1 12340012345678", "pdf_url": "https://...", "expire_at": "2026-03-12", "amount": 97.90, "status": "pending" }
Exiba barcode, pdf_url e expire_at na sua UI. O cliente paga e o status é atualizado (consulta ou webhook).

GET
/api/v1/payments/{order_id}
Consulta o status de um pedido criado pela sua aplicação.

Resposta 200
json
{ "order_id": 456, "status": "completed", "amount": 97.90, "email": "cliente@email.com", "gateway": "efi", "gateway_id": "tx_xxx", "metadata": {}, "created_at": "2026-03-09T10:00:00.000000Z", "updated_at": "2026-03-09T10:05:00.000000Z" }
Se o pedido não existir ou não pertencer à aplicação autenticada: 404.

Idempotência
Para evitar criar o mesmo pagamento duas vezes (ex.: retry após timeout), use idempotency key:

Envie no body: "idempotency_key": "seu-uuid-ou-string-unica" ou no header: Idempotency-Key: seu-uuid-ou-string-unica
Máximo 128 caracteres.
Para a mesma aplicação e mesma chave, a API retorna a mesma resposta (cache por até 24h) sem criar novo pedido.
Recomendado

Use idempotency key em todos os endpoints de criação de pagamento (PIX, cartão, boleto).
Eventos
Se a aplicação tiver webhook_url configurada, a Getfy envia um POST para essa URL quando certos eventos ocorrem em pedidos criados por essa aplicação.

Evento	Descrição
order.completed	Pagamento concluído (pedido pago)
order.pending	Pedido criado ou aguardando pagamento (ex.: PIX/boleto gerados)
order.refunded	Pedido estornado
Formato do payload
O body é JSON, por exemplo:

json
{ "event": "order.completed", "order_id": 456, "amount": 97.90, "status": "completed", "email": "cliente@email.com", "metadata": { "external_id": "ref-123" }, "created_at": "2026-03-09T10:00:00.000000Z", "updated_at": "2026-03-09T10:05:00.000000Z" }
Assinatura (X-Getfy-Signature)
Se a aplicação tiver webhook secret configurado, cada POST inclui o header X-Getfy-Signature: HMAC-SHA256 do body bruto (string JSON) usando o webhook secret como chave.

Como validar no seu servidor:

Ler o body bruto da requisição (antes de parsear JSON).
Calcular HMAC-SHA256(body_bruto, webhook_secret).
Comparar com o valor do header X-Getfy-Signature (comparação constante para evitar timing attacks).
Produção

Se não houver webhook secret configurado, o header não é enviado. Em produção, é recomendado configurar o secret e validar a assinatura.
Boas práticas (webhooks)
Responder com 2xx rapidamente; processar o evento de forma assíncrona se necessário.
Não confiar no conteúdo sem validar a assinatura quando o secret estiver configurado.
Tratar eventos duplicados (mesmo order_id/evento pode ser reenviado em retentativas).
Códigos de erro
Código	Significado
401	Chave de API ausente ou inválida (header Bearer / X-API-Key).
403	Aplicação inativa ou IP não permitido.
404	Recurso não encontrado (ex.: pedido que não pertence à aplicação).
422	Validação falhou (dados inválidos, produto não encontrado, etc.). O body pode incluir message e detalhes.
429	Muitas requisições (rate limit). Tente novamente após o tempo indicado nos headers de resposta.
500	Erro interno do servidor.
Respostas de erro costumam ser JSON, por exemplo: { "message": "Missing or invalid API key." }

Boas práticas gerais
Idempotency key: use em todas as criações de pagamento (PIX, cartão, boleto) para evitar cobranças duplicadas em retentativas.
Webhook: configure webhook_url e webhook_secret; valide sempre o header X-Getfy-Signature quando o secret estiver definido.
API key: nunca inclua a API key em código frontend ou em repositórios; use variáveis de ambiente no backend.
HTTPS: em produção, utilize apenas HTTPS.
Tratamento de erros: trate 4xx e 5xx e implemente retry com backoff para 5xx e 429 (respeitando o rate limit).
Logs: não registre a API key em logs; use apenas identificadores da aplicação (ex.: id ou nome).
Resumo de endpoints
Método	Endpoint	Descrição
POST	/api/v1/checkout/sessions	Criar sessão Checkout Pro (retorna link)
POST	/api/v1/payments/pix	Criar pagamento PIX (transparente)
POST	/api/v1/payments/card	Criar pagamento com cartão
POST	/api/v1/payments/boleto	Criar pagamento com boleto
GET	/api/v1/payments/{order_id}	Consultar status do pedido
Base URL: a raiz da sua instalação Getfy. Todas as rotas da API estão sob o prefixo /api/v1.