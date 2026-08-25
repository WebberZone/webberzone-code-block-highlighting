---
slug: code-block-highlighting-developer-reference
title: "Code Block Highlighting Developer Reference"
products: [code-block-highlighting]
sections: ["03-wzcbh-developer-docs"]
tags: [code-block-highlighting, developer, filters, hooks]
status: publish
toc: true
---

Developer reference for [WebberZone Code Block Highlighting](https://webberzone.com/plugins/webberzone-code-block-highlighting/).

[toc]

## PHP wrapper functions

### `wzcbh_get_settings()`

Returns all plugin settings merged with defaults.

**Returns:** `array`

---

### `wzcbh_get_option( $key, $default_value )`

Returns the value of a single setting key, or the default value if the key does not exist.

```php
$mode = wzcbh_get_option( 'highlighting-mode', 'client' );
```

**Parameters:**

- `$key` *(string)* — The setting key.
- `$default_value` *(mixed, optional)* — Value to return if the key does not exist. Default `null`.

**Returns:** `mixed`

---

### `wzcbh_update_option( $key, $value )`

Updates a single setting key in the database and in the in-memory settings array. Passing an empty, false, or null value removes the key from the settings array.

**Parameters:**

- `$key` *(string)* — The setting key.
- `$value` *(string|bool|int)* — The value to set.

**Returns:** `bool` — `true` on success, `false` on failure.

---

### `wzcbh_delete_option( $key )`

Removes a setting key from the database and from the in-memory settings array.

**Parameters:**

- `$key` *(string)* — The setting key to remove.
