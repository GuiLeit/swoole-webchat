## 📘 Especificação Técnica — Clone do WhatsApp com PHP Swoole + Redis

### 🧩 Visão Geral

O sistema é uma aplicação de mensagens em tempo real inspirada no WhatsApp, construída com:

- **Backend:** PHP + Swoole WebSocket Server
- **Armazenamento:** Redis (AOF habilitado para persistência)
- **Comunicação em tempo real:** WebSocket
- **Frontend:** Web ou Mobile consumindo via WebSocket

Objetivo: permitir mensagens privadas (DM), chats públicos e listagem de usuários online, de forma leve, performática e totalmente em memória.

## 🔁 Fluxo de Dados na Aplicação

### 1. Login/Autenticação

1. O usuário realiza login (via telefone, e-mail ou token temporário).
2. O servidor (HTTP ou WS inicial) gera e salva um token temporário:
    
    ```
    token:{token} -> user_id (TTL: 1h)
    ```
    
3. O cliente conecta ao WebSocket e envia:
    
    ```
    { "action": "auth", "data": { "username": "Fulano", "avatar_url": "profile.avatar.com" } }
    ```
    
4. O servidor valida o token e retorna:
    
    ```
    { "type": "auth_ok", "token": "<jwt-ou-token>", "user_id" : "<uuid>", "chats": ["chat123", "chat456"] }
    ```
    
5. Client adiciona o token ao header de todas as requisições

### 2. Conexão e Presença

- Ao autenticar, o servidor:
    - Marca o usuário como online (`SADD presence:online_users {user_id}`)
    - Define `last_seen:{user_id}`
    - Carrega todos os chats do usuário e associa o socket às salas correspondentes
- Quando o usuário fecha a conexão:
    - Remove seu `fd` das salas
    - Atualiza `last_seen:{user_id}`
    - Remove o usuário da presença se ele não tiver mais conexões ativas

### 3. Envio de Mensagem (Fluxo)

1. Usuário envia:
    
    ```
    { "action": "send_message", "data": { "chat_id": "chat123", "body": "Olá, tudo bem?" } }
    ```
    
2. Servidor:
    - Valida se o usuário é membro do chat
    - Incrementa contador `seq`
    - Cria objeto de mensagem e adiciona à lista `chat:{id}:messages`
    - Publica no canal Redis `broadcast:chat:{id}` para entrega em todos os workers
3. Todos os clientes conectados (membros do chat) recebem:
    
    ```
    {
      "type": "message_created",
      "chat_id": "chat123",
      "message": {
        "id": 42,
        "sender_id": "user_1",
        "body": "Olá, tudo bem?",
        "msg_type": "text",
        "created_at": 1729431812
      }
    }
    ```
    

### 4. Entrega e Leitura

- O cliente que enviou recebe:
    
    ```
    { "type": "ack", "chat_id": "chat123", "client_msg_id": "temp-uuid", "server_msg_id": 42 }
    ```
    
- Quando um usuário lê a mensagem, ele envia:
    
    ```
    { "action": "read_receipt", "data": { "chat_id": "chat123", "message_id": 42 } }
    ```
    
- Todos os membros recebem:
    
    ```
    { "type": "read_receipt", "chat_id": "chat123", "message_id": 42, "user_id": "user_2", "read_at": 1729432000 }
    ```
    

### 5. Chat Público

- Qualquer usuário pode entrar:
    
    ```
    { "action": "join_chat", "data": { "chat_id": "public_chat_1" } }
    ```
    
- O servidor adiciona o usuário no set de membros e envia:
    
    ```
    { "type": "joined", "chat_id": "public_chat_1" }
    ```
    
- Todos no chat recebem:
    
    ```
    { "type": "chat_member_added", "chat_id": "public_chat_1", "user_id": "user_1" }
    ```
    

### 6. Fluxo de DM (Chat Privado)

1. Cliente A solicita:
    
    ```
    { "action": "create_dm", "data": { "peer_user_id": "user_B" } }
    ```
    
2. Backend:
    - Verifica se já existe um chat em `dm:{low}:{high}`
    - Cria novo chat caso não exista
    - Notifica ambos os usuários:
        
        ```
        { "type": "dm_ready", "chat_id": "chat_abc123" }
        ```
        

### 7. Notificações de “digitando”

- Cliente envia:
    
    ```
    { "action": "typing", "data": { "chat_id": "chat123", "is_typing": true } }
    ```
    
- Servidor grava com TTL curto (`typing:{chat_id}:{user_id}` = "1", expira em 5s)
- Todos no chat recebem:
    
    ```
    { "type": "typing", "chat_id": "chat123", "user_id": "user_1", "is_typing": true }
    ```
    

## 🗃️ Estrutura de Armazenamento no Redis

| Chave | Tipo | Descrição |
| --- | --- | --- |
| `user:{user_id}` | Hash | Informações básicas do usuário |
| `presence:online_users` | Set | Todos os usuários online |
| `token:{token}` | String | Token de autenticação para WS |
| `last_seen:{user_id}` | String | Último timestamp online |
| `chat:{chat_id}` | Hash | Metadados do chat |
| `chat:{chat_id}:members` | Set | Usuários participantes |
| `chat:{chat_id}:messages` | List | Históricos recentes (JSON serializado) |
| `chat:{chat_id}:reads` | Hash | Última mensagem lida por cada usuário |
| `chat:{chat_id}:mute` | Hash | Membros silenciados com timestamps |
| `user_chats:{user_id}` | Set | Chats que o usuário participa |
| `dm:{user_low}:{user_high}` | String | Chat ID único para cada par de usuários |
| `chats:public` | Set | Todos os chats públicos |
| `typing:{chat_id}:{user_id}` | String | Status de digitação (TTL curto) |
| `broadcast:chat:{chat_id}` | Pub/Sub | Canal de entrega entre processos |
| `rate:msg:{user_id}` | Counter | Controle simples de spam |

---

## 📡 Eventos WebSocket

### 🔹 Cliente → Servidor

| Evento | Payload | Descrição |
| --- | --- | --- |
| `auth` | `{ token }` | Autenticar a conexão |
| `create_dm` | `{ peer_user_id }` | Cria chat privado (ou recupera se já existir) |
| `create_public_chat` | `{ title }` | Cria um novo canal público |
| `join_chat` | `{ chat_id }` | Entra em um chat público |
| `send_message` | `{ chat_id, body, type, client_msg_id }` | Envia mensagem para um chat |
| `read_receipt` | `{ chat_id, message_id }` | Marca mensagem como lida |
| `typing` | `{ chat_id, is_typing }` | Notifica status de digitação |

---

### 🔹 Servidor → Cliente

| Evento | Exemplo | Descrição |
| --- | --- | --- |
| `auth_ok` | `{ user_id, chats }` | Autenticação bem-sucedida |
| `error` | `{ error }` | Erros genéricos / validação |
| `dm_ready` | `{ chat_id }` | Chat privado criado com outro usuário |
| `chat_created` | `{ chat_id, title }` | Chat público criado |
| `joined` | `{ chat_id }` | Confirmação de entrada em chat |
| `chat_member_added` | `{ chat_id, user_id }` | Novo membro entrou |
| `message_created` | `{ chat_id, message }` | Mensagem recebida/enviada |
| `ack` | `{ client_msg_id, server_msg_id, chat_id }` | Confirmação de mensagem enviada |
| `read_receipt` | `{ chat_id, message_id, user_id, read_at }` | Mensagem marcada como lida |
| `typing` | `{ chat_id, user_id, is_typing }` | Usuário digitando |
| `presence` | `{ user_id, status }` | Usuário ficou online/offline (opcional) |

---

## 💻 Comportamento do Frontend

### Autenticação e Conexão

- Enviar token ao conectar.
- Armazenar `user_id` e lista inicial de chats do evento `auth_ok`.
- Mostrar presença (online/offline) e “último visto”.

### Chats e Mensagens

- Para cada `chat_id`, abrir listener do WS.
- Quando chegar `message_created`:
    - Adicionar a mensagem na lista local.
    - Marcar como entregue se for do próprio usuário.
    - Se o chat não estiver aberto, sinalizar notificação (badge/som).

### Leitura

- Ao abrir o chat, enviar `read_receipt` com última mensagem.
- Atualizar estado de leitura visualmente (✓✓ / “visto”).

### Digitando

- Mostrar “usuário está digitando...” se receber `typing:true`.
- Ocultar após `typing:false` ou timeout de 5s.

### Chats Públicos

- Exibir lista de chats públicos (`chats:public` via HTTP ou WS).
- Após `join_chat`, começa a receber `message_created` do canal.

### Usuários Online

- Atualizar status ao receber `presence` events.
- Exibir “Online” / “Visto por último há X min”.

### Reconexão

- Reenviar `auth` e sincronizar chats (`user_chats:{user_id}`).
- Pedir histórico recente (ex: via endpoint REST, `LRANGE chat:{id}:messages 0 20`).