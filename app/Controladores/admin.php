<?php

// admin.php — panel de administración: entrar, agregar libros y borrarlos.

define('ADMIN_USUARIO', 'admin');
define('ADMIN_PASSWORD', 'admin');

class Admin
{
    private $metodo;
    private $accion;
    private $seccion;
    private $libro;
    private $sesion;

    public function __construct($metodo, $accion, $seccion, $sesion)
    {
        $this->metodo = $metodo;
        $this->accion = $accion;
        $this->seccion = $seccion;
        $this->libro = new Libro();
        $this->sesion = $sesion;
    }

    /** Atiende toda la petición y muestra o redirige según corresponda. */
    public function atender()
    {
        if ($this->metodo === 'POST' && $this->accion === 'login') {
            $this->entrar();
        }
        if ($this->metodo === 'POST' && $this->accion === 'logout') {
            $this->salir();
        }

        if (!$this->sesion->esAdmin()) {
            $error = null;
            if (filter_input(INPUT_GET, 'login') !== null) {
                $error = 'Usuario o contraseña incorrectos.';
            }
            $this->verLogin($error);
            return;
        }

        if ($this->metodo === 'POST' && in_array($this->accion, array('add-product', 'update-stock', 'delete-book'), true)) {
            $this->guardarLibro();
        }

        $expired = filter_input(INPUT_GET, 'expired');
        if ($expired !== null) {
            $this->verPanel('Tu sesión expiró. Recarga la página e inténtalo de nuevo.', false, $this->seccion);
            return;
        }
        $mensajeError = filter_input(INPUT_GET, 'error');
        if ($mensajeError !== null) {
            $this->verPanel((string) $mensajeError, false, $this->seccion);
            return;
        }
        if (filter_input(INPUT_GET, 'added') !== null) {
            $this->verPanel('El libro #' . (int) filter_input(INPUT_GET, 'id') . ' se agregó para la venta.', true, 'add');
            return;
        }
        if (filter_input(INPUT_GET, 'stock_updated') !== null) {
            $this->verPanel('Se actualizó el stock del libro #' . (int) filter_input(INPUT_GET, 'id') . '.', true, 'manage');
            return;
        }
        if (filter_input(INPUT_GET, 'deleted') !== null) {
            $this->verPanel('El libro #' . (int) filter_input(INPUT_GET, 'id') . ' se eliminó del catálogo.', false, 'manage');
            return;
        }
        $this->verPanel(null, false, $this->seccion);
    }

    /** Prueba quién intenta entrar: el admin o una cuenta de lector. */
    private function entrar()
    {
        $username = trim((string) filter_input(INPUT_POST, 'username'));
        $password = (string) filter_input(INPUT_POST, 'password');
        $recordarme = filter_input(INPUT_POST, 'remember') === '1';

        if ($username === ADMIN_USUARIO && $password === ADMIN_PASSWORD) {
            $cuentaAdmin = new Usuario();
            $adminId = $cuentaAdmin->obtenerOCrearAdmin(ADMIN_USUARIO, ADMIN_PASSWORD);
            $this->sesion->entrarAdmin($adminId, 'Administrador');
            $this->sesion->configurarRecordarme($adminId, $recordarme);
            Respuesta::redirigir('admin.php');
        }

        $usuario = new Usuario();
        $cuenta = $usuario->autenticar($username, $password);
        if ($cuenta !== null) {
            if (Sesion::rolEsAdmin($cuenta['rol'] ?? '')) {
                $this->sesion->entrarAdmin($cuenta['id'], $cuenta['nombre']);
                $this->sesion->configurarRecordarme($cuenta['id'], $recordarme);
                Respuesta::redirigir('admin.php');
            }
            $this->sesion->entrarUsuario($cuenta['id'], $cuenta['nombre']);
            $this->sesion->configurarRecordarme($cuenta['id'], $recordarme);
            Respuesta::redirigir('./');
        }

        if (filter_input(INPUT_POST, 'login_origin') === 'home') {
            Respuesta::redirigir('./?login=error');
        }
        Respuesta::redirigir('admin.php?login=error');
    }

    /** Cierra la sesión y vuelve al inicio. */
    private function salir()
    {
        $this->sesion->salir();
        Respuesta::redirigir('./');
    }

    /** Guarda el resultado de agregar o borrar un libro y redirige al panel. */
    private function guardarLibro()
    {
        $idioma = (string) filter_input(INPUT_POST, 'language');
        $idTexto = (string) filter_input(INPUT_POST, 'id');
        $caratula = null;

        try {
            if ($this->accion === 'add-product') {
                $caratula = $this->subirCaratula();
                $datos = array();
                $datos['titulo'] = (string) filter_input(INPUT_POST, 'title');
                $datos['autor'] = (string) filter_input(INPUT_POST, 'author');
                $datos['cover'] = $caratula;
                $datos['precio'] = (string) filter_input(INPUT_POST, 'price');
                $datos['stock'] = (string) filter_input(INPUT_POST, 'stock');

                $id = $this->libro->agregarProducto($datos);
                Respuesta::redirigir('admin.php?section=add&added=1&id=' . $id);
            }

            if ($this->accion === 'update-stock') {
                if (!ctype_digit($idTexto)) {
                    throw new InvalidArgumentException('El libro seleccionado no es válido.');
                }
                $id = (int) $idTexto;
                $this->libro->actualizarStock($id, $idioma, (string) filter_input(INPUT_POST, 'stock'));
                Respuesta::redirigir('admin.php?section=manage&stock_updated=1&id=' . $id);
            }

            if (!is_numeric($idTexto)) {
                throw new InvalidArgumentException('El libro seleccionado no es válido.');
            }
            $id = (int) $idTexto;
            $this->libro->borrar($idioma, $id);
            Respuesta::redirigir('admin.php?section=manage&deleted=1&id=' . $id);
        } catch (InvalidArgumentException $error) {
            $this->descartarCaratula($caratula);
            Respuesta::redirigir('admin.php?section=' . $this->seccion . '&error=' . rawurlencode($error->getMessage()));
        } catch (Exception $error) {
            $this->descartarCaratula($caratula);
            error_log($error->getMessage());
            Respuesta::redirigir('admin.php?section=' . $this->seccion . '&error=' . rawurlencode('Ocurrió un error al procesar la solicitud.'));
        }
    }

    /** Valida y guarda una foto de carátula cargada por el administrador. */
    private function subirCaratula()
    {
        $archivo = $_FILES['cover'] ?? null;
        if (!is_array($archivo) || !isset($archivo['error']) || $archivo['error'] === UPLOAD_ERR_NO_FILE) {
            throw new InvalidArgumentException('Selecciona una foto de carátula.');
        }
        if ($archivo['error'] === UPLOAD_ERR_INI_SIZE || $archivo['error'] === UPLOAD_ERR_FORM_SIZE) {
            throw new InvalidArgumentException('La foto no puede superar los 2 MB.');
        }
        if ($archivo['error'] !== UPLOAD_ERR_OK || empty($archivo['tmp_name']) || !is_uploaded_file($archivo['tmp_name'])) {
            throw new InvalidArgumentException('No se pudo recibir la foto de carátula.');
        }

        $tamano = filesize($archivo['tmp_name']);
        if ($tamano === false || $tamano < 1 || $tamano > 2 * 1024 * 1024) {
            throw new InvalidArgumentException('La foto debe pesar hasta 2 MB.');
        }

        $tipos = array(
            'image/jpeg' => 'jpg',
            'image/png' => 'png',
            'image/webp' => 'webp',
        );
        $informacion = getimagesize($archivo['tmp_name']);
        $mime = $informacion['mime'] ?? '';
        if ($informacion === false || !isset($tipos[$mime])) {
            throw new InvalidArgumentException('La carátula debe ser una imagen JPG, PNG o WebP.');
        }

        $directorio = PROJECT_ROOT . '/fotos';
        if (!is_dir($directorio) || !is_writable($directorio)) {
            throw new RuntimeException('No se puede guardar la foto de carátula.');
        }
        $nombre = 'libro-' . bin2hex(random_bytes(16)) . '.' . $tipos[$mime];
        $ruta = $directorio . '/' . $nombre;
        if (!move_uploaded_file($archivo['tmp_name'], $ruta)) {
            throw new RuntimeException('No se pudo guardar la foto de carátula.');
        }

        return 'fotos/' . $nombre;
    }

    /** Retira la foto temporal si el alta del producto no llegó a completarse. */
    private function descartarCaratula($ruta)
    {
        if ($ruta === null) {
            return;
        }
        $archivo = PROJECT_ROOT . '/' . str_replace('/', DIRECTORY_SEPARATOR, $ruta);
        if (is_file($archivo) && !unlink($archivo)) {
            error_log('No se pudo retirar la carátula temporal: ' . $archivo);
        }
    }

    /** Muestra la pantalla de ingreso del panel. */
    private function verLogin($error = null)
    {
        require PROJECT_ROOT . '/app/Vistas/admin-login.php';
    }

    /** Muestra el panel con sus mensajes y su lista de libros. */
    private function verPanel($message = null, $success = false, $section = 'home')
    {
        $books = array();
        if ($section === 'manage') {
            $books = $this->libro->resumen();
        }
        $sesion = $this->sesion;
        require PROJECT_ROOT . '/app/Vistas/admin-panel.php';
    }
}

$metodo = $_SERVER['REQUEST_METHOD'];
$accion = (string) filter_input(INPUT_POST, 'action');
$seccion = (string) filter_input(INPUT_GET, 'section');
if ($seccion !== 'home' && $seccion !== 'add' && $seccion !== 'manage') {
    $seccion = 'home';
}

$admin = new Admin($metodo, $accion, $seccion, $sesion);
$admin->atender();
