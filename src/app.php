<?php
require __DIR__ . '/../vendor/autoload.php';
use Slim\Factory\AppFactory;

$app = AppFactory::create();
$app->addBodyParsingMiddleware();


$categorias = [
    ['id'=>1,'nome'=>'Alimentação','tipo'=>'despesa'],
    ['id'=>2,'nome'=>'Lazer','tipo'=>'despesa'],
    ['id'=>3,'nome'=>'Transporte','tipo'=>'despesa'],
    ['id'=>4,'nome'=>'Salário','tipo'=>'receita'],
];
$lancamentos = [
    ['id'=>1,'descricao'=>'Mercado','valor'=>150.0,'data'=>'2024-07-01','tipo'=>'despesa','categoria_id'=>1],
    ['id'=>2,'descricao'=>'Salário julho','valor'=>3500.0,'data'=>'2024-07-05','tipo'=>'receita','categoria_id'=>4],
];

if (!function_exists('json')) {
    function json($response, $dados, $status = 200) {
        $response->getBody()->write(json_encode($dados, JSON_UNESCAPED_UNICODE));
        return $response->withHeader('Content-Type', 'application/json')->withStatus($status);
    }
}

if (!function_exists('buscarPorId')) {
    function buscarPorId(array $lista, int $id) {
        foreach ($lista as $item) if ($item['id'] === $id) return $item;
        return null;
    }
}

if (!function_exists('proximoId')) {
    function proximoId(array $lista) {
        return empty($lista) ? 1 : max(array_column($lista, 'id')) + 1;
    }
}

if (!function_exists('dataValida')) {
    function dataValida(string $data): bool {
        $d = DateTime::createFromFormat('!Y-m-d', $data);
        return $d && $d->format('Y-m-d') === $data;
    }
}

$app->get('/status', fn($rq,$rs) => json($rs, ['status'=>'online']));
$app->get('/nome',   fn($rq,$rs) => json($rs, ['nome'=>'Maria Eduarda Andrade']));
$app->get('/musica', fn($rq,$rs) => json($rs, ['musica'=>'Bohemian Rhapsody - Queen']));
$app->get('/filme',  fn($rq,$rs) => json($rs, ['filme'=>'Interestelar']));
$app->get('/tic',    fn($rq,$rs) => json($rs, ['tic'=>'Código Limpo']));

//Listar
$app->get('/categorias', fn($rq,$rs) => json($rs, ['categorias'=>$categorias]));

//Buscar por ID
$app->get('/categorias/{id}', function($rq,$rs,$a) use ($categorias) {
    $c = buscarPorId($categorias, (int)$a['id']);
    return $c ? json($rs, $c) : json($rs, ['erro'=>'Categoria não encontrada'], 404);
});

//Criar
$app->post('/categorias', function($rq,$rs) use (&$categorias) {
    $d = (array)$rq->getParsedBody();
    $nome = trim($d['nome'] ?? '');
    $tipo = strtolower(trim($d['tipo'] ?? ''));

    if ($nome === '' || !in_array($tipo, ['receita','despesa']))
        return json($rs, ['erro'=>'Informe nome e tipo (receita/despesa)'], 400);

    foreach ($categorias as $c)
        if (strcasecmp($c['nome'], $nome) === 0)
            return json($rs, ['erro'=>'Nome de categoria já existe'], 409);

    $nova = ['id'=>proximoId($categorias),'nome'=>$nome,'tipo'=>$tipo];
    $categorias[] = $nova;
    return json($rs, $nova, 201);
});

//Atualizar
$app->put('/categorias/{id}', function($rq,$rs,$a) use (&$categorias, $lancamentos) {
    $id = (int)$a['id'];
    $d = (array)$rq->getParsedBody();
    $nome = trim($d['nome'] ?? '');
    $tipo = strtolower(trim($d['tipo'] ?? ''));

    if ($nome === '' || !in_array($tipo, ['receita','despesa']))
        return json($rs, ['erro'=>'Dados inválidos'], 400);

    foreach ($categorias as $i => $c) {
        if ($c['id'] !== $id) continue;
        foreach ($categorias as $outra)
            if ($outra['id'] !== $id && strcasecmp($outra['nome'], $nome) === 0)
                return json($rs, ['erro'=>'Nome já existe'], 409);
        foreach ($lancamentos as $l)
            if ($l['categoria_id'] === $id && $l['tipo'] !== $tipo)
                return json($rs, ['erro'=>'Tipo imutável: há lançamentos'], 409);
        $categorias[$i] = ['id'=>$id,'nome'=>$nome,'tipo'=>$tipo];
        return json($rs, $categorias[$i]);
    }
    return json($rs, ['erro'=>'Categoria não encontrada'], 404);
});

//Excluir
$app->delete('/categorias/{id}', function($rq,$rs,$a) use (&$categorias, $lancamentos) {
    $id = (int)$a['id'];
    foreach ($categorias as $i => $c) {
        if ($c['id'] !== $id) continue;
        foreach ($lancamentos as $l)
            if ($l['categoria_id'] === $id)
                return json($rs, ['erro'=>'Categoria possui lançamentos vinculados'], 409);
        array_splice($categorias, $i, 1);
        return json($rs, ['mensagem'=>'Categoria excluída']);
    }
    return json($rs, ['erro'=>'Categoria não encontrada'], 404);
});

//Listar com filtros
$app->get('/lancamentos', function($rq,$rs) use ($lancamentos) {
    $f = $rq->getQueryParams();
    $res = array_filter($lancamentos, function($l) use ($f) {
        if (isset($f['data_inicio'])  && $l['data'] < $f['data_inicio']) return false;
        if (isset($f['data_fim'])     && $l['data'] > $f['data_fim'])    return false;
        if (isset($f['categoria_id']) && $l['categoria_id'] !== (int)$f['categoria_id']) return false;
        if (isset($f['tipo'])         && $l['tipo'] !== strtolower($f['tipo'])) return false;
        return true;
    });
    return json($rs, ['lancamentos'=>array_values($res)]);
});

//Buscar por ID
$app->get('/lancamentos/{id}', function($rq,$rs,$a) use ($lancamentos) {
    $l = buscarPorId($lancamentos, (int)$a['id']);
    return $l ? json($rs, $l) : json($rs, ['erro'=>'Lançamento não encontrado'], 404);
});

// Criar
$app->post('/lancamentos', function($rq,$rs) use (&$lancamentos, $categorias) {
    $d = (array)$rq->getParsedBody();
    $cat = buscarPorId($categorias, (int)($d['categoria_id'] ?? 0));

    $invalido = trim($d['descricao'] ?? '') === ''
        || !is_numeric($d['valor'] ?? null) || (float)$d['valor'] <= 0
        || !dataValida($d['data'] ?? '')
        || !in_array(strtolower($d['tipo'] ?? ''), ['receita','despesa'])
        || !$cat || $cat['tipo'] !== strtolower($d['tipo']);

    if ($invalido)
        return json($rs, ['erro'=>'Dados inválidos ou categoria incompatível'], 400);

    $novo = [
        'id'=>proximoId($lancamentos),
        'descricao'=>trim($d['descricao']),
        'valor'=>(float)$d['valor'],
        'data'=>$d['data'],
        'tipo'=>strtolower($d['tipo']),
        'categoria_id'=>$cat['id']
    ];
    $lancamentos[] = $novo;
    return json($rs, $novo, 201);
});

//Atualizar
$app->put('/lancamentos/{id}', function($rq,$rs,$a) use (&$lancamentos, $categorias) {
    $id = (int)$a['id'];
    $d = (array)$rq->getParsedBody();
    foreach ($lancamentos as $i => $l) {
        if ($l['id'] !== $id) continue;
        $novo = array_merge($l, $d);
        $cat = buscarPorId($categorias, (int)($novo['categoria_id'] ?? 0));

        $invalido = trim($novo['descricao'] ?? '') === ''
            || !is_numeric($novo['valor'] ?? null) || (float)$novo['valor'] <= 0
            || !dataValida($novo['data'] ?? '')
            || !in_array($novo['tipo'] ?? '', ['receita','despesa'])
            || !$cat || $cat['tipo'] !== $novo['tipo'];

        if ($invalido)
            return json($rs, ['erro'=>'Dados inválidos'], 400);

        $lancamentos[$i] = $novo;
        return json($rs, $novo);
    }
    return json($rs, ['erro'=>'Lançamento não encontrado'], 404);
});

//Excluir
$app->delete('/lancamentos/{id}', function($rq,$rs,$a) use (&$lancamentos) {
    foreach ($lancamentos as $i => $l) {
        if ($l['id'] === (int)$a['id']) {
            array_splice($lancamentos, $i, 1);
            return json($rs, ['mensagem'=>'Lançamento excluído']);
        }
    }
    return json($rs, ['erro'=>'Lançamento não encontrado'], 404);
});

// Saldo atual
$app->get('/resumo/saldo', function($rq,$rs) use ($lancamentos) {
    $rec = 0; $des = 0;
    foreach ($lancamentos as $l)
        $l['tipo'] === 'receita' ? $rec += $l['valor'] : $des += $l['valor'];
    return json($rs, ['total_receitas'=>$rec,'total_despesas'=>$des,'saldo'=>$rec - $des]);
});

// Totais por período
$app->get('/resumo/periodo', function($rq,$rs) use ($lancamentos) {
    $f = $rq->getQueryParams();
    $ini = $f['data_inicio'] ?? ''; $fim = $f['data_fim'] ?? '';
    if (!dataValida($ini) || !dataValida($fim) || $ini > $fim)
        return json($rs, ['erro'=>'Datas inválidas (AAAA-MM-DD)'], 400);

    $rec = 0; $des = 0;
    foreach ($lancamentos as $l)
        if ($l['data'] >= $ini && $l['data'] <= $fim)
            $l['tipo'] === 'receita' ? $rec += $l['valor'] : $des += $l['valor'];

    return json($rs, ['data_inicio'=>$ini,'data_fim'=>$fim,'total_receitas'=>$rec,'total_despesas'=>$des,'saldo'=>$rec - $des]);
});

return $app;