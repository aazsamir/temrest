# Temrest

Temrest is a package for building RESTful APIs with [Tempest framework](https://github.com/tempestphp/tempest-framework).

_It's still in early FAFO development stage._

## Usage

```
composer require aazsamir/temrest:dev-main
```

Temrest reads route definitions directly from your types.

Given this example code:

```php
<?php
class ListRequest implements Request
{
    use IsRequest;

    public int $limit = 30;
    public ?int $page = null;
}

class ListResponse implements ApiResponse
{
    use IsApiResponse;

    /** @return Pet[] */
    public function toResponse(): array;
    {
        return $this->pets;
    }
}

class PetController
{
    #[Get('/owner/{ownerId}/pets')]
    #[ApiInfo(description: 'List pets by their owner')]
    public function listPets(string $ownerId, ListRequest $request): ListResponse
    {
        // ...
    }
}

class Pet
{
    use ToArray;

    public string $id;
    public string $name;
    public PetType $type;
    /** @var string[] */
    public array $tags;
}

enum PetType: string
{
    case Dog = 'dog';
    case Cat = 'cat';
}
```

When you run `./tempest openapi:generate` it will generate an OpenAPI specifilaction

```yaml
openapi: 3.0.0
servers: []
info:
  title: Temrest API
  version: '1.0'
paths:
  /owner/{ownerId}/pets:
    get:
      description: List pets by their owner
      responses:
        '200':
          description: Successful Response
          content:
            application/json:
              schema:
                type: array
                items:
                  $ref: '#/components/schemas/Pet'
                nullable: false
      parameters:
        - name: ownerId
          in: path
          required: true
          schema:
            type: string
        - name: limit
          in: query
          schema:
            type: integer
            nullable: true
        - name: page
          in: query
          schema:
            type: integer
            nullable: true
components:
  schemas:
    Pet:
      type: object
      properties:
        id:
          type: string
          nullable: false
        name:
          type: string
          nullable: false
        type:
          $ref: '#/components/schemas/PetType'
      nullable: false
    PetType:
      type: string
      enum:
        - dog
        - cat
      nullable: false
```

## How it works

Temrest reads all defined routes and
- their path parameters (`/api/users/{id}`)
- type-hinted request parameters (`public function update(UpdateUserRequest $request)`)
- type-hinted response types (`public function list(): ListUsersResponse`) that implement `ApiResponse` interface, by reflecting on `toResponse()` method
- recursively traverses properties of request and response types to generate OpenAPI schemas

## Known Limitations

Given that it was hacked on during weekend, Temrest lacks support for
- nullable arrays
- nested generics
- dictionary types
- advanced validation rules (e.g. minLength, maximum, pattern, etc.)
- authentication schemes
- tests :]