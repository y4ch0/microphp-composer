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
<?php if ($users): ?>
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
            <?php foreach ($users as $user): ?>
                <tr>
                    <td><?php echo htmlspecialchars($user['id']); ?></td>
                    <td><?php echo htmlspecialchars($user['imie']); ?></td>
                    <td><?php echo htmlspecialchars($user['nazwisko']); ?></td>
                    <td><?php echo htmlspecialchars($user['login']); ?></td>
                    <td><?php echo htmlspecialchars($user['uprawnienia']); ?></td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
<?php else: ?>
    <p>No users found in the database.</p>
<?php endif; ?>

<h3>Wypożyczenia</h3>
<?php if ($lends): ?>
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
            <?php foreach ($lends as $lend): ?>
                <tr>
                    <td><?php echo htmlspecialchars($lend['id']); ?></td>
                    <td><?php echo htmlspecialchars($lend['imie'])." ".htmlspecialchars($lend['nazwisko']); ?></td>
                    <td><?php echo htmlspecialchars($lend['autor']).": ".htmlspecialchars($lend['tytul']); ?></td>
                    <td><?php echo "od ".htmlspecialchars($lend['okres_od'])." do ".htmlspecialchars($lend['okres_do']); ?></td>
                    <td><?php echo htmlspecialchars($lend['stan']); ?></td>
                    <td><?php echo $lend['notatka']; ?></td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
<?php else: ?>
    <p>No lends found in the database.</p>
<?php endif; ?>
