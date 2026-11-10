<?php
use PHPUnit\Framework\TestCase;
use Slim\Psr7\Factory\ServerRequestFactory;

class ControleFinanceiroTest extends TestCase
{
    private function req($metodo, $rota, $dados = null) {
        $app = require __DIR__ . '/../src/app.php';
        $r = (new ServerRequestFactory())->createServerRequest($metodo, $rota);
        if ($dados !== null) {
            $r = $r->withHeader('Content-Type', 'application/json');
            $r->getBody()->write(json_encode($dados));
            $r->getBody()->rewind();
        }
        return $app->handle($r);
    }

    //Categorias
    public function testListarCategorias() {
        $this->assertSame(200, $this->req('GET','/categorias')->getStatusCode());
    }
    public function testCategoriaInexistente() {
        $this->assertSame(404, $this->req('GET','/categorias/999')->getStatusCode());
    }
    public function testCriarCategoria() {
        $this->assertSame(201, $this->req('POST','/categorias',
            ['nome'=>'Pets','tipo'=>'despesa'])->getStatusCode());
    }
    public function testCategoriaDuplicada() {
        $this->assertSame(409, $this->req('POST','/categorias',
            ['nome'=>'Lazer','tipo'=>'despesa'])->getStatusCode());
    }
    public function testExcluirCategoriaComLancamento() {
        $this->assertSame(409, $this->req('DELETE','/categorias/1')->getStatusCode());
    }

    //Lançamentos
    public function testListarLancamentos() {
        $this->assertSame(200, $this->req('GET','/lancamentos')->getStatusCode());
    }
    public function testFiltrarPorTipo() {
        $r = $this->req('GET','/lancamentos?tipo=receita');
        foreach (json_decode((string)$r->getBody(), true)['lancamentos'] as $l)
            $this->assertSame('receita', $l['tipo']);
    }
    public function testCriarLancamento() {
        $this->assertSame(201, $this->req('POST','/lancamentos', [
            'descricao'=>'Conta de luz','valor'=>120,'data'=>'2024-07-20',
            'tipo'=>'despesa','categoria_id'=>3
        ])->getStatusCode());
    }
    public function testValorZeroInvalido() {
        $this->assertSame(400, $this->req('POST','/lancamentos', [
            'descricao'=>'X','valor'=>0,'data'=>'2024-07-20',
            'tipo'=>'despesa','categoria_id'=>1
        ])->getStatusCode());
    }
    public function testCategoriaIncompativel() {
        $this->assertSame(400, $this->req('POST','/lancamentos', [
            'descricao'=>'X','valor'=>10,'data'=>'2024-07-20',
            'tipo'=>'receita','categoria_id'=>1
        ])->getStatusCode());
    }
    public function testLancamentoInexistente() {
        $this->assertSame(404, $this->req('GET','/lancamentos/999')->getStatusCode());
    }

    //Resumo
    public function testSaldo() {
        $r = $this->req('GET','/resumo/saldo');
        $this->assertSame(200, $r->getStatusCode());
        $this->assertArrayHasKey('saldo', json_decode((string)$r->getBody(), true));
    }
    public function testPeriodoInvalido() {
        $this->assertSame(400, $this->req('GET',
            '/resumo/periodo?data_inicio=2024-07-31&data_fim=2024-07-01')->getStatusCode());
    }
}