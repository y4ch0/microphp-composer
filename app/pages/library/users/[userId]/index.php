<?php
$userId = $request->route('userId');
$user = \MicroPHP\Database::table('uzytkownik')->where(['id' => $userId])->first();
?>

<?php if($user): ?>
<h1>Dane o użytkowniku</h1>
<table>
    <thead>
        <tr>
            <th>Kolumna</th>
            <th>Wartość</th>
        </tr>
    </thead>
    <tbody>
        <tr>
            <th>Imię</th>
            <td><?php echo htmlspecialchars($user["imie"]) ?></td>
        </tr>
        <tr>
            <th>Drugie imię</th>
            <td><?php if($user["drugie_imie"] !== null) {echo htmlspecialchars(string: $user["drugie_imie"]);}  ?></td>
        </tr>
        <tr>
            <th>Nazwisko</th>
            <td><?php echo htmlspecialchars($user["nazwisko"]) ?></td>
        </tr>
        <tr>
            <th>Adres</th>
            <td><?php echo htmlspecialchars($user["adres_ulica"])." ".htmlspecialchars($user["adres_nrdomu"]).", ".htmlspecialchars($user["adres_miejscowosc"])." ".htmlspecialchars($user["adres_kodpocztowy"]) ?></td>
        </tr>
        <tr>
            <th>Nr karty</th>
            <td><?php echo htmlspecialchars($user["nr_karty"]) ?></td>
        </tr>
        <tr>
            <th>Login</th>
            <td><?php echo htmlspecialchars($user["login"]) ?></td>
        </tr>
        <tr>
            <th>Typ konta</th>
            <td><?php echo htmlspecialchars($user["uprawnienia"]) ?></td>
        </tr>
    </tbody>
</table>
<?php else: ?>
<h1>Nie znaleziono użytkownika</h1>
<?php endif;?>
