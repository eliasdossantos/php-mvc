<?php

namespace Framework\Database;

/**
 * Contrato base dos Seeders executados pelo CLI do framework.
 * O SeedRunner inicializa o ambiente e envolve cada run() em uma transação.
 */
abstract class Seeder
{
    /** Executa a inicialização de dados deste Seeder. */
    abstract public function run(): void;
}
