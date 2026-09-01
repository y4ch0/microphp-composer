<?php
$username = "Testing 123";
$role = "admin";
$array = [
    "test1",
    "test2",
    "test3",
    "test4",
    "test5",
    true,
    0,
    21.37
];
if(!isset($_GET["objectValue"])) {
    $object = null;
} else {
    $object = $_GET["objectValue"];
}
?>

<h1>Welcome, {{ $username }}</h1>

@if($role == "admin")
    <p>You have administrator privileges.</p>
@else
    <p>You are a regular user.</p>
@endif

<ul>
@foreach($array as $item)
    <li>{{ $item }}</li>
@endforeach
</ul>

@component("lorem", ["title" => "Testing arguments for components"])

@component("lorem")

@isset($object)
<p>You have set an object: <b>{{ $object }}</b></p>
@else
<p>You have not set an object</p>
@endisset

@php
$counter = 7;
$numbers = [1,2,3,4,5,6,7,8,9,10,11,12,13,14,15];
$isPrimary = true;
$enabled = true;
$error = true;
$important = false;
@endphp

@for($i = 0; $i < 3; $i++)
  <p>Loop {{ $i }}</p>
@endfor

@while($counter > 0)
  <p>Counter: {{ $counter }}</p>
  <?php $counter--; ?>
@endwhile

@foreach($numbers as $n)
  @continue($n < 5)
  @break($n > 10)
  <p>{{ $n }}</p>
@endforeach

{{-- This is a template comment and will not appear in the HTML. --}}

<button @class([
  'btn',
  'btn-primary' => $isPrimary,
  'btn-disabled' => !$enabled,
])>
  Click me
</button>

<div @style([
  'color:red' => $error,
  'font-weight:bold' => $important,
  'margin:10px'
])>
  Styled text
</div>

@php
$locked = true;
$value = "testing assigning value";
$isChecked = true;
$selectedId = 3;
@endphp

<input type="text" @disabled(!$enabled) @readonly($locked) @value($username)>

<input type="number" @value("2135")>

<input type="checkbox" @checked($isChecked)>

<select>
    @for($i = 0; $i <= 10; $i++)
        <option @value($i) @selected($i == $selectedId)>{{ $i }}</option>
    @endfor
</select>
