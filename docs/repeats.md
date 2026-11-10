# Repeats

A `var="name[]"` node is a sample row. PHP clones it once per item; the sample itself is removed at render, so an unused repeat renders nothing.

```html
<table>
  <tbody>
    <tr var="user[]">
      <td var="name"></td>
      <td><span class="badge" var="admin?">Admin</span></td>
    </tr>
  </tbody>
</table>
```

## Adding rows

```php
foreach ($users as $user) {
    $row = $t->repeat('user');
    $row->text('name', $user->name);
    $row->show('admin', $user->isAdmin());
    $row->append();
}
```

Or the short form:

```php
$t->each('user', $users, fn(Row $row, User $user) => $row->text('name', $user->name));
```

- A row has the full template API, scoped to the row: vars inside it never clash with vars elsewhere on the page.
- A row can change its own element by the repeat's name (`$row->attr('user', 'id', …)`).
- `append()` adds the row where the sample is, after earlier rows. `prepend()` adds it before them.
- Once added, a row is locked.
- `each()` appends each row unless the callback already added it.

## Rows somewhere else

`appendTo()` and `prependTo()` add a row inside another var. The name is looked up in the template the row belongs to, then outward. Every node with that name gets a copy.

```html
<ul var="nav">
  <li var="item[]"><a var="link"></a></li>
  <li var="divider[]" class="divider"></li>
</ul>
```
```php
foreach ($menu as $entry) {
    $row = $entry->isDivider() ? $t->repeat('divider') : $t->repeat('item');
    $entry->isDivider() || $row->text('link', $entry->name);
    $row->appendTo('nav');
}
```

This is how one list mixes several kinds of rows. Rows added with `append()` go where their sample is; rows added with `appendTo()` go at the end of the target.

## Nesting

Rows can hold repeats of their own, as deep as the markup goes:

```php
$tr = $t->repeat('tr');
$tr->each('td', $cells, fn(Row $td, string $c) => $td->text('td', $c));
$tr->append();
```

A recursive menu (`item[]` inside `item[]`) works too. There, a row can't target its own element by name, because the inner repeat uses it. For a tree of unknown depth, insert a menu template into itself level by level.

## Speed

Rows clone the sample node; they don't re-parse anything. 1,000 rows of a three-cell table render in about 20 ms.
