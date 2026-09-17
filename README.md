# Property Listing API

A RESTful API for managing property listings — built with Laravel and Sanctum. Users can register, authenticate, and manage property listings (create, update, delete their own; browse and search everyone's), including uploading and managing property images.

This is a portfolio project demonstrating a token-authenticated REST API with role-based ownership, policy-based authorization, search/filter/pagination, file uploads, and automated test coverage.

## Tech Stack

| Layer | Technology |
|---|---|
| Language | PHP 8.3 |
| Framework | Laravel 13 (v13.32) |
| Authentication | Laravel Sanctum v4 (token-based) |
| Database | MySQL |
| Testing | PHPUnit (feature tests) |
| Code Style | Laravel Pint |
| Local environment | Laragon (Windows) |

## Features

- Token-based authentication (register, login, logout, current user)
- Role-based users (`admin`, `agent`, `buyer`)
- Full property CRUD, with writes restricted to the listing's owner via a policy
- Search and filtering by city, price range, bedrooms, listing type, property type, and status
- Paginated property listings (15 per page by default)
- Property image upload/delete, with primary-image handling
- Consistent JSON response envelope on property/image endpoints
- Feature-tested (auth, property CRUD, ownership, filters, image upload)

## Project Structure

```
app/
├── Enums/                    # PropertyListingType, PropertyType, PropertyStatus, UserRole
├── Http/
│   ├── Controllers/Api/      # AuthController, PropertyController, PropertyImageController
│   ├── Requests/             # Form request validation classes
│   └── Resources/            # API resource transformers
├── Models/                   # User, Property, PropertyImage
├── Policies/                 # PropertyPolicy (ownership rules)
└── Services/                 # ImageUploadService
```

## Local Setup (Laragon / Windows)

### Prerequisites

- PHP 8.3+
- Composer
- MySQL
- [Laragon](https://laragon.org/) (or any local server stack serving PHP + MySQL)

### Installation

1. **Clone the repository** into your Laragon `www` directory:

   ```bash
   cd F:\laragon\www
   git clone <repository-url> property-listing-api
   cd property-listing-api
   ```

2. **Install dependencies:**

   ```bash
   composer install
   ```

3. **Configure environment:**

   ```bash
   cp .env.example .env
   php artisan key:generate
   ```

   Edit `.env` with your database credentials:

   ```env
   DB_CONNECTION=mysql
   DB_HOST=127.0.0.1
   DB_PORT=3306
   DB_DATABASE=property_listing_api
   DB_USERNAME=root
   DB_PASSWORD=
   ```

4. **Create the database**, then run migrations:

   ```bash
   php artisan migrate
   ```

5. **Create the storage symlink** (required for uploaded images to be publicly reachable):

   ```bash
   php artisan storage:link
   ```

6. **Run the test suite** (optional, confirms everything is wired correctly):

   ```bash
   php artisan test
   ```

### Running the API

With Laragon, the app is served automatically at:

```
http://localhost/property-listing-api/public
```

Alternatively, use the built-in server:

```bash
php artisan serve
```

which serves the API at `http://127.0.0.1:8000`.

All endpoints below are shown relative to your chosen base URL, e.g. `http://localhost/property-listing-api/public/api`.

## Authentication Flow

Authentication uses **Laravel Sanctum** personal access tokens (not sessions/cookies) — suited for external API clients like Postman, mobile apps, or a decoupled frontend.

1. **Register** or **log in** to receive a plain-text bearer token.
2. **Send the token** on every subsequent protected request:

   ```
   Authorization: Bearer <token>
   ```

3. **Log out** to revoke the current token server-side.

Public endpoints (browsing properties) require no token. Write endpoints (creating/editing/deleting properties or images) require a valid token, and further require the requesting user to **own** the resource for update/delete actions.

## Response Formats

Every endpoint (auth, properties, images) returns a consistent envelope:

```json
{
  "success": true,
  "message": "Human-readable description of what happened.",
  "data": { }
}
```

**Validation errors** (422) use Laravel's default shape:

```json
{
  "message": "The given data was invalid.",
  "errors": {
    "field_name": ["The field name is required."]
  }
}
```

## API Endpoints

### Auth

#### `POST /api/register` — public

Registers a new user and returns an access token.

**Request:**

```json
{
  "name": "Jane Doe",
  "email": "jane@example.com",
  "password": "password123",
  "password_confirmation": "password123",
  "role": "agent"
}
```

`role` is optional — one of `admin`, `agent`, `buyer` (defaults to `buyer`).

**Response `201 Created`:**

```json
{
  "success": true,
  "message": "User registered successfully.",
  "data": {
    "user": {
      "id": 1,
      "name": "Jane Doe",
      "email": "jane@example.com",
      "role": "agent",
      "created_at": "2026-09-15T22:10:00.000000Z"
    },
    "token": "1|abcdEFGh1234IjklMnOpQrStUvWxYz"
  }
}
```

#### `POST /api/login` — public

**Request:**

```json
{
  "email": "jane@example.com",
  "password": "password123"
}
```

**Response `200 OK`:**

```json
{
  "success": true,
  "message": "Logged in successfully.",
  "data": {
    "user": {
      "id": 1,
      "name": "Jane Doe",
      "email": "jane@example.com",
      "role": "agent",
      "created_at": "2026-09-15T22:10:00.000000Z"
    },
    "token": "2|zYxWvUtSrQpOnMlKjIhGfEdCbA9876"
  }
}
```

Invalid credentials return `401 Unauthorized`.

#### `POST /api/logout` — requires `Authorization: Bearer <token>`

Revokes the token used on the current request.

**Response `200 OK`:**

```json
{
  "success": true,
  "message": "Logged out successfully.",
  "data": null
}
```

#### `GET /api/me` — requires `Authorization: Bearer <token>`

Returns the authenticated user's profile.

**Response `200 OK`:**

```json
{
  "success": true,
  "message": "User retrieved successfully.",
  "data": {
    "user": {
      "id": 1,
      "name": "Jane Doe",
      "email": "jane@example.com",
      "role": "agent",
      "created_at": "2026-09-15T22:10:00.000000Z"
    }
  }
}
```

### Properties

#### `GET /api/properties` — public

Lists properties with optional filters and pagination.

| Query param | Type | Description |
|---|---|---|
| `city` | string | Partial, case-insensitive match |
| `min_price` / `max_price` | numeric | Price range |
| `bedrooms` | integer | Minimum number of bedrooms |
| `type` | `sale` \| `rent` | Listing type |
| `property_type` | `residential` \| `commercial` | Property type |
| `status` | `active` \| `inactive` \| `sold` | Listing status |
| `per_page` | integer (max 100) | Results per page (default 15) |

**Example:** `GET /api/properties?city=Austin&min_price=200000&max_price=600000&bedrooms=3&type=sale`

**Response `200 OK`:**

```json
{
  "success": true,
  "message": "Properties retrieved successfully.",
  "data": {
    "data": [
      {
        "id": 12,
        "title": "Cozy Family Home",
        "description": "A lovely 3-bed home near downtown.",
        "type": "sale",
        "property_type": "residential",
        "price": "350000.00",
        "bedrooms": 3,
        "bathrooms": 2,
        "area": "1800.00",
        "address": "123 Main St",
        "city": "Austin",
        "state": "TX",
        "zip_code": "78701",
        "status": "active",
        "created_at": "2026-09-15T22:18:00.000000Z",
        "updated_at": "2026-09-15T22:18:00.000000Z"
      }
    ],
    "links": {
      "first": "http://localhost/property-listing-api/public/api/properties?page=1",
      "last": "http://localhost/property-listing-api/public/api/properties?page=3",
      "prev": null,
      "next": "http://localhost/property-listing-api/public/api/properties?page=2"
    },
    "meta": {
      "current_page": 1,
      "per_page": 15,
      "total": 42,
      "last_page": 3
    }
  }
}
```

#### `GET /api/properties/{property}` — public

Returns a single property, including its owner and images.

**Response `200 OK`:**

```json
{
  "success": true,
  "message": "Property retrieved successfully.",
  "data": {
    "id": 12,
    "title": "Cozy Family Home",
    "description": "A lovely 3-bed home near downtown.",
    "type": "sale",
    "property_type": "residential",
    "price": "350000.00",
    "bedrooms": 3,
    "bathrooms": 2,
    "area": "1800.00",
    "address": "123 Main St",
    "city": "Austin",
    "state": "TX",
    "zip_code": "78701",
    "status": "active",
    "owner": {
      "id": 1,
      "name": "Jane Doe",
      "email": "jane@example.com",
      "role": "agent",
      "created_at": "2026-09-15T22:10:00.000000Z"
    },
    "images": [
      {
        "id": 5,
        "url": "http://localhost/property-listing-api/public/storage/properties/12/a1b2c3.jpg",
        "is_primary": true,
        "created_at": "2026-09-15T22:20:00.000000Z"
      }
    ],
    "created_at": "2026-09-15T22:18:00.000000Z",
    "updated_at": "2026-09-15T22:18:00.000000Z"
  }
}
```

#### `POST /api/properties` — requires auth

Creates a property owned by the authenticated user.

**Request:**

```json
{
  "title": "Cozy Family Home",
  "description": "A lovely 3-bed home near downtown.",
  "type": "sale",
  "property_type": "residential",
  "price": 350000,
  "bedrooms": 3,
  "bathrooms": 2,
  "area": 1800,
  "address": "123 Main St",
  "city": "Austin",
  "state": "TX",
  "zip_code": "78701"
}
```

`status` is optional (defaults to `active`).

**Response `201 Created`** — same shape as `GET /api/properties/{property}`.

#### `PUT` / `PATCH /api/properties/{property}` — requires auth, owner only

All fields optional (partial updates supported). Same field rules as create.

**Request:**

```json
{ "price": 340000, "status": "inactive" }
```

**Response `200 OK`** — updated property, same shape as `GET /api/properties/{property}`.

Returns `403 Forbidden` if the authenticated user does not own the property.

#### `DELETE /api/properties/{property}` — requires auth, owner only

**Response:** `204 No Content` on success, `403 Forbidden` if not the owner.

### Property Images

#### `POST /api/properties/{property}/images` — requires auth, owner only

Multipart form upload.

| Field | Type | Rules |
|---|---|---|
| `image` | file | required, image, mimes: jpg/jpeg/png/webp, max 2MB |
| `is_primary` | boolean | optional |

**Example (`curl`):**

```bash
curl -X POST "http://localhost/property-listing-api/public/api/properties/12/images" \
  -H "Authorization: Bearer 1|abcdEFGh1234IjklMnOpQrStUvWxYz" \
  -F "image=@house.jpg" \
  -F "is_primary=true"
```

Setting `is_primary=true` automatically unsets `is_primary` on every other image for that property.

**Response `201 Created`:**

```json
{
  "success": true,
  "message": "Image uploaded successfully.",
  "data": {
    "id": 5,
    "url": "http://localhost/property-listing-api/public/storage/properties/12/a1b2c3.jpg",
    "is_primary": true,
    "created_at": "2026-09-15T22:20:00.000000Z"
  }
}
```

Returns `403 Forbidden` if the authenticated user does not own the property, `422 Unprocessable Content` for an invalid file.

#### `DELETE /api/properties/{property}/images/{image}` — requires auth, owner only

Deletes the image file from disk and its database record.

**Response:** `204 No Content` on success.

Returns `403 Forbidden` if not the owner, `404 Not Found` if the image doesn't belong to the given property.

## Running Tests

```bash
php artisan test
```

Feature tests cover authentication, property CRUD and filtering, ownership-based authorization, and image upload/delete — including edge cases like non-owners attempting writes and cross-property image access.

## Code Style

This project uses [Laravel Pint](https://laravel.com/docs/pint) for consistent formatting:

```bash
vendor/bin/pint
```

## License

This project is open-sourced software for portfolio and educational purposes.
