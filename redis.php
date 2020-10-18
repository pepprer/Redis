<?php
$redis = new Redis();
$redis->connect('127.0.0.1', 6379);

function findUser($email)
{
    global $redis;
    $userCount = $redis->get('userCount');
    for ($i = 1; $i <= $userCount; $i++) {
        $user = json_decode($redis->get('user_' . $i), true);
        if ($email == $user['email']) {
            return $user;
        }
    }
    return false;
}

function findProduct($name)
{
    global $redis;
    $productCount = $redis->get('productCount');

    for ($i = 1; $i <= $productCount; $i++) {
        $product = json_decode($redis->get('product_' . $i), true);
        if ($name == $product['name']) {
            return $product;
        }
    }
    return false;
}

function addProduct()
{
    global $redis;
    $productCount = $redis->get('productCount');
    $redis->watch('productCount');
    if (empty($productCount)) $productCount = 0;
    $name = readline("Įveskite produkto pavadinima: ");
    if (!findProduct($name)) {
        $price = readline("Įveskite produkto kaina: ");
        $productCount++;
        $act = $redis->multi()
            ->set('product_' . $productCount, json_encode(['id' => $productCount, 'name' => $name, 'price' => $price, 'amount' => rand(0, 20)], true))
            ->set('productCount', $productCount)
            ->exec();
        if ($act) {
            echo "Produktas pridetas! \n";
        } else {
            echo "Įvyko klaida \n";
        }
        $redis->unwatch();
    } else {
        echo "Toks produkas egzistuoja! \n";
    }
}

function addToWarehouse()
{
    global $redis, $user;
    $product = findProduct(readline("Produkto pavadinimas: "));
    if ($product) {
        if ($product['amount'] > 0) {
            $redis->watch('product_' . $product['id']);
            echo 'Turime: ' . $product['amount'] . "vnt. \n";
            $amount = readline("Kiek jums reikia: ");
            while ($amount > $product['amount'] || $amount == 0) {
                if ($amount == 0) {
                    echo "Negali būti 0! \n";
                } else {
                    echo "Tiek vienetų neturime! \n";
                }
                $amount = readline("Įveskite dar kartą: ");
            }
            $product['amount'] -= $amount;
            $warehouse = $redis->get('warehouse_' . $user['id'] . '_' . $product['id']);
            if (empty($warehouse)) {
                $user['warehouse']++;
                $act = $redis->multi()
                    ->set('user_' . $user['id'], json_encode($user, true))
                    ->set('product_' . $product['id'], json_encode($product, true))
                    ->set('warehouse_' . $user['id'] . '_' . $product['id'], $amount)
                    ->exec();
            } else {
                $warehouse['count'] += $amount;
                $act = $redis->multi()
                    ->set('user_' . $user['id'], json_encode($user, true))
                    ->set('product_' . $product['id'], json_encode($product, true))
                    ->set('warehouse_' . $user['id'] . '_' . $product['id'], $amount)
                    ->exec();
            }
            $redis->unwatch();
            if (!$act) {
                echo "Įvyko klaida \n";
            }
        } else {
            echo "Deja šio produkto neturime! \n";
        }
    } else {
        echo "Toks produkas egzistuoja! \n";
    }
}

function ownWarehouse()
{
    global $redis, $user;
    $productCount = $redis->get('productCount');
    $one = false;
    for ($i = 1; $i <= $productCount; $i++) {
        $item = $redis->get('warehouse_' . $user['id'] . '_' . $i);
        if (!empty($item)) {
            $product = json_decode($redis->get('product_' . $i), true);
            echo $product['name'] . ' ' . $item . " vnt. \n";
            $one = true;
        }
    }
    if(!$one) echo "Jūsų sandėlis tuščias! \n";
}

    echo "Turite paskyra? \n";
    $loginRegister = '';
    do {
        $loginRegister = readline("Įveskite 'taip' arba 'ne': ");
    } while ($loginRegister !== 'taip' && $loginRegister !== 'ne');


    $successfully = true;
    if ($loginRegister == 'taip') {
        echo "Prisijungimas \n";
        $email = readline("Paštas: ");
        $password = readline("Slaptažodis: ");
        $user = findUser($email);
        if ($user) {
            if ($user['password'] !== $password) {
                $successfully = false;
            }
        } else {
            $successfully = false;
        }
    } else {
        echo "Registracija \n";
        do {
            $email = readline("Paštas: ");
            $user = findUser($email);
            if ($user) {
                echo "Paštas užimtas! \n";
                $email = '';
            }
        } while ($email == '');

        $userCount = $redis->get('userCount');
        if (empty($userCount)) $userCount = 0;
        $redis->watch('userCount');
        $password = readline("Slaptažodis: ");
        $userCount++;
        $act = $redis->multi()
            ->set('user_' . $userCount, json_encode(['id' => $userCount, 'email' => $email, 'password' => $password, 'warehouse' => 0], true))
            ->set('userCount', $userCount)
            ->exec();
        if ($act) {
            echo "Sėkmingai užsiregistravote! \n";
        } else {
            $successfully = false;
            echo "Įvyko klaida \n";
        }
        $redis->unwatch();
        $user = ['id' => $userCount, 'email' => $email, 'password' => $password, 'warehouse' => 0];
    }

    if ($successfully) {
        echo "Operacijų sąrašas: \n";
        echo "1. Pridėti produktą \n";
        echo "2. Gauti informacija apie produktą \n";
        echo "3. Pridėti į savo sandėlį \n";
        echo "4. Pažiūrėti savo sandėlį \n";
        echo "5. Uždaryti programą \n";
        do {
            echo "Ką norite atlikti: \n";
            $action = readline("Operacijos skaičius: ");
            switch ($action) {
                case '1':
                    addProduct();
                    break;
                case '2':
                    $product = findProduct(readline("Produkto pavadinimas: "));
                    if ($product) {
                        echo "Pavadinimas: " . $product['name'] . "\n";
                        echo "Kaina: " . $product['name'] . "\n";
                        echo "Kiekis: " . $product['amount'] . "\n";
                    } else {
                        echo "Toks produkas neegzistuoja! \n";
                    }
                    break;
                case '3':
                    addToWarehouse();
                    break;
                case '4':
                    ownWarehouse();
                    break;
            }
        } while ($action !== '5');
    } else {
        echo "Prisijungti nepavyko!";
    }
