swagger: '2.0'
info:
  version: 0.0.2
  title: Clubinho Mágico
consumes:
  - application/json
produces:
  - application/json
paths:
  /token:
    post:
      description: >-
        Solicita um access token API JWT. Este token deverá ser usado nas
        requisições futuras
      parameters:
        - name: username
          in: body
          required: true
          type: string
        - name: password
          in: body
          required: true
          type: string
          format: password
      responses:
        '200':
          description: API access token
          schema:
            $ref: '#/definitions/User'
  /token/valid:
    post:
      description: Verifica se o token é valido
      header:
        - name: Authorization
          required: true
      responses:
        '200':
          description: API access token
          schema:
            $ref: '#/definitions/User'
        '403':
          code: jwt_auth_invalid_token
          description: Expired token
  /me:
    get:
      description: Retorna os dados do usuário logado
      security:
        oauth2: admin write read public
      responses:
        '200':
          description: Sucesso
          schema:
            $ref: '#/definitions/User'
        '401':
          description: Unauthorized response
          schema:
            $ref: '#/definitions/User'
        default:
          description: Unexpected error
          schema:
            $ref: '#/definitions/ErrorModel'
    put:
      summary: Atualiza os dados de perfil do usuário logado
      parameters:
        - name: name
          in: body
          required: true
          type: string
        - name: cpf
          in: body
          required: true
          type: integer
        - name: email
          in: body
          required: true
          type: string
          format: email
        - name: address
          in: body
          required: false
          type: string
        - name: phone
          in: body
          required: false
          type: string
        - name: zipcode
          in: body
          required: false
        - name: password
          in: body
          required: false
          description: Senha atual
        - name: password_new
          in: body
          required: false
          description: Nova senha
      responses:
        '200':
          description: Sucesso
          schema:
            $ref: '#/definitions/User'
        '401':
          description: Unauthorized response
          schema:
            $ref: '#/definitions/User'
        default:
          description: Unexpected error
          schema:
            $ref: '#/definitions/ErrorModel'
  /child/:
    post:
      description: Adicionar uma criança
      security:
        oauth2: public read write admin
      parameters:
        - name: name
          in: body
          required: true
          type: string
        - name: age
          in: body
          required: true
          type: integer
          format: int32
        - name: avatar
          in: body
          required: true
          type: string
          format: ana/luiz/maria
      responses:
        '200':
          description: Sucesso
          schema:
            $ref: '#/definitions/Child'
        '401':
          description: Unauthorized response
          schema:
            $ref: '#/definitions/Child'
        default:
          description: Unexpected error
          schema:
            $ref: '#/definitions/ErrorModel'
  '/child/{id}':
    delete:
      description: Deletar uma criança
      security:
        oauth2: public read write admin
      parameters:
        - name: id
          description: Id da crianca
          in: pady
          required: true
          type: integer
      responses:
        '200':
          description: Sucesso
          schema:
            $ref: '#/definitions/Child'
        '401':
          description: Unauthorized response
          schema:
            $ref: '#/definitions/Child'
        default:
          description: Unexpected error
          schema:
            $ref: '#/definitions/ErrorModel'
    put:
      description: Atualizar os dados de uma criança
      security:
        oauth2: public read write admin
      parameters:
        parameters:
          - name: id
            description: Id da crianca
            in: query
            required: true
            type: integer
          - name: name
            in: body
            required: true
            type: string
          - name: age
            in: body
            required: true
            type: integer
            format: int32
          - name: name
            in: body
            required: true
            type: string
          - name: avatar
            in: body
            required: true
            type: string
            format: ana/luiz/maria
  '/child/{id}/confirm-event/{eventid}':
    post:
      description: |
        Confirma a presença de uma criança a um evento e atualiza sua pontuação
      security:
        oauth2: public read write admin
      parameters:
        - name: eventid
          in: query
          description: ID do evento
          required: true
          type: number
          format: double
      responses:
        '202':
          description: Sucesso
          schema: null
          $ref: '#/definitions/Child'
        '401':
          description: Unauthorized response
          schema:
            $ref: '#/definitions/Child'
        '404':
          description: Event doesn't exist
          schema:
            $ref: '#/definitions/ErrorModel'
        default:
          description: Unexpected error
          schema:
            $ref: '#/definitions/ErrorModel'
  /get-schedule-list:
    get:
      description: >
        Retorna a lista de **eventos** da **agenda**.

        O query parametro opcional **size** determina a quantidade máxima no
        array de retorno. Necessário para controlar de não vir mais eventos do
        que o necessário
      parameters:
        - name: size
          in: query
          description: Tamanho do array
          required: true
          type: number
          format: double
      responses:
        '200':
          description: Retorno com sucesso
          schema:
            $ref: '#/definitions/Event'
  /reset-password:
    post:
      description: Resetar a senha
      parameters:
        - name: email
          in: query
          description: Email do usuário que deseja trocar a senha
          required: true
          type: string
          format: email
      responses:
        '200':
          description: Sucesso
        default:
          description: Unexpected error
          schema:
            $ref: '#/definitions/ErrorModel'
  /me/chage-password:
    post:
      description: Resetar a senha
      parameters:
        - name: password
          in: body
          description: Nova senha do usuário
          required: true
          type: string
          format: pasword
        - name: old-password
          in: body
          description: Antiga senha do usuário
          required: true
          type: string
          format: pasword
      responses:
        '200':
          description: Sucesso
        default:
          description: Unexpected error
          schema:
            $ref: '#/definitions/ErrorModel'
definitions:
  Event:
    type: object
    properties:
      id:
        type: integer
      title:
        type: string
      author:
        type: string
      datetime:
        type: string
        format: date-time
      excerpt:
        type: string
      content:
        type: string
  Child:
    type: object
    properties:
      id:
        type: integer
      name:
        type: string
      age:
        type: integer
        format: int32
        minimum: 0
      avatar:
        type: string
        description: 'valores possíveis: ana, luiz, maria'
      events:
        type: array
        items:
          $ref: '#/definitions/Event'
      points:
        type: integer
      created_at:
        type: string
        format: dateTime
  User:
    type: object
    properties:
      id:
        type: number
      name:
        type: integer
      cpf:
        type: integer
        description: apenas números
      email:
        type: string
        format: email
      phone:
        type: string
      address:
        type: string
      children:
        type: array
        items:
          $ref: '#/definitions/Child'
      facebook_user:
        type: bool
        description: >-
          Logo após o cadastro via facebook, é necessário completar os dados do
          usuário. Essa flag informa se isso o usuário veio do Facebook
  ErrorModel:
    required: code message
    properties:
      code:
        type: integer
        minimum: 100
        maximum: 600
      message:
        type: string
