<?php
require_once __DIR__ . '/php/layout.php';
require_once __DIR__ . '/php/libros.php';

exigir_rol(['administrador', 'bibliotecario']);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verificar_csrf();
    $accion = $_POST['accion'] ?? '';
    if ($accion === 'crear_libro') {
        $res = crear_libro($pdo, $_POST, $_FILES['portada'] ?? null);
        flash_set($res['ok'] ? 'ok' : 'error', $res['msg']);
    } elseif ($accion === 'crear_autor') {
        $nom = trim($_POST['nombre'] ?? '');
        if ($nom !== '') {
            try {
                $pdo->prepare("INSERT INTO autores (nombre, nacionalidad) VALUES (?, ?)")->execute([$nom, trim($_POST['nacionalidad'] ?? '') ?: null]);
                flash_set('ok', "Autor '{$nom}' registrado.");
            } catch (PDOException $e) {
                flash_set('error', 'Ese autor ya existe.');
            }
        }
    } elseif ($accion === 'crear_categoria') {
        $nom = trim($_POST['nombre'] ?? '');
        if ($nom !== '') {
            try {
                $pdo->prepare("INSERT INTO categorias (nombre) VALUES (?)")->execute([$nom]);
                flash_set('ok', "Categoría '{$nom}' creada.");
            } catch (PDOException $e) {
                flash_set('error', 'Esa categoría ya existe.');
            }
        }
    } elseif ($accion === 'eliminar_libro') {
        $res = eliminar_libro($pdo, (int)($_POST['libro_id'] ?? 0));
        flash_set($res['ok'] ? 'ok' : 'error', $res['msg']);
    }
    header('Location: catalogo_admin.php');
    exit;
}

$autores = listar_autores($pdo);
$categorias = listar_categorias($pdo);
$libros = buscar_catalogo($pdo);

render_head('Gestión de Libros — SIBI');
render_topbar('libros');
?>
<main class="container">
  <h1 style="font-size:28px;margin-bottom:18px;">Gestión de Catálogo y Libros</h1>

      <div class="grid-admin-2">
        <div class="card">
          <h3>Registrar nuevo libro</h3>
          <form method="post" enctype="multipart/form-data">
            <input type="hidden" name="csrf_token" value="<?= h(csrf_token()) ?>">
            <input type="hidden" name="accion" value="crear_libro">
            <div class="field"><label>Título *</label><input class="input" name="titulo" required></div>
            <div class="field">
              <label>Portada del libro (opcional)</label>
              <div class="cover-upload-box">
                <img id="new-cover-preview" src="assets/img/portada-default.jpg" alt="Vista previa portada" class="cover-upload-preview">
                <div class="cover-upload-info">
                  <input type="file" name="portada" accept="image/jpeg,image/png,image/webp" data-cover-input="new-cover-preview">
                  <small>JPG, PNG o WebP · Máx. 2 MB</small>
                </div>
              </div>
            </div>
            <div class="field">
              <label>Autor *</label>
              <select class="input" name="autor_id" required>
                <option value="">Seleccionar autor...</option>
                <?php foreach ($autores as $a): ?>
                  <option value="<?= (int)$a['id'] ?>"><?= h($a['nombre']) ?></option>
                <?php endforeach; ?>
              </select>
            </div>
            <div class="field">
              <label>Categoría *</label>
              <select class="input" name="categoria_id" required>
                <option value="">Seleccionar categoría...</option>
                <?php foreach ($categorias as $c): ?>
                  <option value="<?= (int)$c['id'] ?>"><?= h($c['nombre']) ?></option>
                <?php endforeach; ?>
              </select>
            </div>
            <div class="grid-2">
              <div class="field"><label>ISBN</label><input class="input" name="isbn"></div>
              <div class="field"><label>Año</label><input class="input" type="number" name="anio_publicacion" placeholder="2024"></div>
            </div>
            <div class="field"><label>Editorial</label><input class="input" name="editorial"></div>
            <div class="field"><label>Código primer ejemplar</label><input class="input" name="codigo_ejemplar" placeholder="Ej. REC-PRG-010"></div>
            <div class="field"><label>Sinopsis</label><textarea class="input" name="sinopsis" rows="2"></textarea></div>
            <button type="submit" class="btn btn-primary btn-block">Guardar libro</button>
          </form>
        </div>

        <div>
          <div class="grid-2 mb-16">
            <form method="post" class="card">
              <input type="hidden" name="csrf_token" value="<?= h(csrf_token()) ?>">
              <input type="hidden" name="accion" value="crear_autor">
              <h3 style="font-size:15px;">+ Autor rápido</h3>
              <div class="field"><input class="input" name="nombre" required placeholder="Nombre del autor"></div>
              <button class="btn btn-navy btn-sm" type="submit">Agregar autor</button>
            </form>
            <form method="post" class="card">
              <input type="hidden" name="csrf_token" value="<?= h(csrf_token()) ?>">
              <input type="hidden" name="accion" value="crear_categoria">
              <h3 style="font-size:15px;">+ Categoría rápida</h3>
              <div class="field"><input class="input" name="nombre" required placeholder="Ej. Redes y Seguridad"></div>
              <button class="btn btn-navy btn-sm" type="submit">Agregar categoría</button>
            </form>
          </div>

          <div class="card">
            <h3>Libros vigentes (<?= count($libros) ?>)</h3>
            <div class="table-wrap">
              <table>
                <thead><tr><th>Libro</th><th>Autor</th><th>Copias</th><th>Acciones</th></tr></thead>
                <tbody>
                  <?php foreach ($libros as $l): ?>
                  <tr>
                    <td>
                      <div class="book-cell">
                        <img src="<?= h($l['portada']) ?>" alt="<?= h($l['titulo']) ?>" class="book-thumb">
                        <div>
                          <strong><?= h($l['titulo']) ?></strong>
                          <div class="text-muted"><?= h($l['categoria']) ?></div>
                        </div>
                      </div>
                    </td>
                    <td><?= h($l['autor']) ?></td>
                    <td><span class="badge <?= (int)$l['disponibles'] > 0 ? 'badge-green' : 'badge-gray' ?>"><?= (int)$l['disponibles'] ?> / <?= (int)$l['total_ejemplares'] ?></span></td>
                    <td>
                      <div class="actions-cell">
                        <a href="libro.php?id=<?= (int)$l['id'] ?>" class="btn btn-outline btn-sm">Ver / Editar</a>
                        <form method="post" style="display:inline;">
                          <input type="hidden" name="csrf_token" value="<?= h(csrf_token()) ?>">
                          <input type="hidden" name="accion" value="eliminar_libro">
                          <input type="hidden" name="libro_id" value="<?= (int)$l['id'] ?>">
                          <button type="submit" class="btn btn-danger btn-sm" data-confirm="¿Seguro que deseas eliminar '<?= h($l['titulo']) ?>' del catálogo?">Eliminar</button>
                        </form>
                      </div>
                    </td>
                  </tr>
                  <?php endforeach; ?>
                </tbody>
              </table>
            </div>
          </div>
        </div>
      </div>
</main>
<?php render_footer(); ?>
