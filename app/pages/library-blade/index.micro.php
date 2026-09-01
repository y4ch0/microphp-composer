<?php
// Fetch all books from the database
$books = \MicroPHP\Database::table('ksiazka')->get();

// Fetch all users from the database
$users = \MicroPHP\Database::table('uzytkownik')->get();

$lends = \MicroPHP\Database::join(
    'wypozyczenie',
    'uzytkownik',
    'wypozyczenie.id_uzytkownik',
    'uzytkownik.id'
)->join(
    'ksiazka',
    'wypozyczenie.id_ksiazka',
    '=',
    'ksiazka.id'
)->select([
    'wypozyczenie.id AS id',
    'wypozyczenie.okres_od',
    'wypozyczenie.okres_do',
    'wypozyczenie.stan',
    'ksiazka.tytul',
    'ksiazka.id AS ksiazka_id',
    'ksiazka.autor',
    'uzytkownik.imie',
    'uzytkownik.drugie_imie',
    'uzytkownik.nazwisko',
    'wypozyczenie.notatka',
])->get();
?>

<h2>Library Database Viewer</h2>
<p>This page connects to the SQLite database and displays its content.</p>

<hr>

<h3>Użytkownicy</h3>
@if($users)
     <table class="striped">
        <thead>
            <tr>
                <th>ID</th>
                <th>Imię (Name)</th>
                <th>Nazwisko (Surname)</th>
                <th>Login</th>
                <th>Uprawnienia (Permissions)</th>
            </tr>
        </thead>
        <tbody>
            @foreach($users as $user)
                <tr>
                    <td>{{ $user['id'] }}</td>
                    <td>{{ $user['imie'] }}</td>
                    <td>{{ $user['nazwisko'] }}</td>
                    <td>{{ $user['login'] }}</td>
                    <td>{{ $user['uprawnienia'] }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>
@else
    <p>No users found in the database.</p>
@endif

<h3>Wypożyczenia</h3>
@if($lends)
     <table class="striped">
        <thead>
            <tr>
                <th>#</th>
                <th>Czytelnik</th>
                <th>Książka</th>
                <th>Okres wypożyczenia</th>
                <th>Status wypożyczenia</th>
                <th>Notatki</th>
            </tr>
        </thead>
        <tbody>
            @foreach($lends as $lend)
                <tr>
                    <td>{{ $lend['id'] }}</td>
                    <td>{{ $lend['imie'] }} {{ $lend['nazwisko'] }}</td>
                    <td>{{ $lend['autor'] }}: {{ $lend['tytul'] }}</td>
                    <td>od {{ $lend['okres_od'] }} do {{ $lend['okres_do'] }}</td>
                    <td>{{ $lend['stan'] }}</td>
                    <td>@if($lend['notatka'] == null) @else {{ $lend['notatka'] }} @endif</td>
                </tr>
            @endforeach
        </tbody>
    </table>
@else
    <p>No lends found in the database.</p>
@endif
