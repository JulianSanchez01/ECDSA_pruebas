<?php

if ($this->authenticated == true) {
    $b_tipoCert = false;
    $b_tipoDoc = false;
    $b_tipoDocEnt = false;
    $b_vigencia = false;
    $b_testigo = false;
    $b_municipio = false;
    $b_municipioEnt = false;
    $b_email = false;
    $b_emailEnt = false;
    $b_fecha = false;
    $flag = false;

    $tipoCert = $params->tipoCert;
    $tipoDoc = $params->tipoDoc;
    $documento = $params->documento;
    $nombres = $params->nombres;
    $apellidos = $params->apellidos;
    $municipio = $params->municipio;
    $direccion = $params->direccion;
    $email = trim($params->email);
    $telefono = $params->telefono;
    $celular = $params->celular;
    $ocupacion = $params->ocupacion;

    $fechaCert = $params->fechaCert;
    $formato = $params->formato;
    $vigenciaCert = $params->vigenciaCert;
    $testigo = $params->testigo;
    $foto = $params->foto;
    $soporte = $params->soporte;
    $verific_doc = $params->verific_doc;
    $pkcs10 = $params->pkcs10;

    $mensaje = null;
    $estado = 0;
    $conexion = pg_connect("dbname=$this->DB user=$this->USR password=$this->PSWD host=$this->HOST port=$this->PORT ", "PGSQL_CONNECT_FORCE_NEW");
    syslog(LOG_INFO, "Intentando conectar a la BD");
    if ($conexion == FALSE) {
        $mensaje = "No fue posible establecer conexion con la base de datos: ";
        $estado = 401;
        $response = new wsResponse($estado, $mensaje);
    }
    if ($this->VERIFY) {
        $breakLevels = 1;
        include 'VerificDoc.php';
    } else {
        $aprobarEmision = 'false';
        $dataVerificacion = '';
        $puntaje = 0;
    }
    syslog(LOG_INFO, "tipo certificado " . $tipoCert);
    pg_query($conexion, "BEGIN;");
    $sql = sprintf("select * from tiposervicio where id_tipocertificado = %d;", $tipoCert);
    $resultado = pg_query($conexion, $sql);
    if (pg_num_rows($resultado) > 0) {
        $b_tipoCert = true;
    }
    if ($b_tipoCert == true) {
        $DocAdj = base64_decode($soporte);
        if (!empty($soporte)) {
            $fileName = $this->dir_tmp . 'otpdata/DocsTo' . $tipoDoc . $documento . '.zip';
            file_put_contents($fileName, $DocAdj);
            $za = new ZipArchive();
            $za->open($fileName);
            $cantidadArchivos = $za->numFiles;
            $sql = "select d.*
                from documentos d
                join documentoxra dxra on(d.id_tipodocumento = dxra.id_tipodocumento and dxra.id_tipocertificado = $tipoCert and dxra.id_ra = " . $this->ra . ")
                where d.activo=true
                order by nombre";
            $resultado = pg_query($conexion, $sql);
            $rows = pg_num_rows($resultado);
            $sqlxra = sprintf("select d.*
                from documentos d
                join documentosxcertificado dc using(id_tipodocumento)
                where id_tipocertificado=%s and d.activo=true and dc.activo=true and d.opcional_convenio = false
                order by nombre", $tipoCert);
            $resultadoxra = pg_query($conexion, $sqlxra);
            $rowsxra = pg_num_rows($resultadoxra);
            $size = 0;
            $total = $rows + $rowsxra;
            if ($cantidadArchivos == $total) {
                for ($i = 0; $i < $za->numFiles; $i++) {
                    $stat = $za->statIndex($i);
                    $stat['name'] = iconv('ISO-8859-1', 'UTF-8//IGNORE', $stat['name']);
                    $pathinfo = pathinfo($stat['name']);
                    $size += $stat['size'];
                    if (mb_strtolower($pathinfo['extension']) !== 'pdf') {
                        $mensaje = "Error, Los documentos debe ser archivos con formato PDF";
                        $estado = 115;
                        $response = new wsResponse($estado, $mensaje);
                        return;
                    }
                }
                if ($size > 10485760) {
                    $mensaje = "Error, Los documentos no puede ocupar mas de 10Mb de espacio en disco";
                    $estado = 116;
                    $response = new wsResponse($estado, $mensaje);
                    return;
                }
            } else {
                $mensaje = "Error, El zip debe contener $total documentos";
                $estado = 113;
                $response = new wsResponse($estado, $mensaje);
                return;
            }
            unlink($fileName);
        }
    } else {
        $mensaje = "Error, el valor del tipo de certificado es incorrecto";
        $estado = 301;
        $response = new wsResponse($estado, $mensaje);
        return;
    }
    $char_valid = array(" ", "ñ", "á", "é", "í", "ó", "ú", "Á", "É", "Í", "Ó", "Ú", "Ñ", ".", "-", "ä", "ë", "ï", "ö", "ü", "Ä", "Ë", "Ï", "Ö", "Ü", "&", "-", ".", "@", "+", "(", ")");
    $char_invalid = array("'", "\"", "|");
    // Validaciones sobre cada uno de los parámetros, si son obligatorios y tienen un valor especifico.
    $docSint = "/^[a-zA-Z0-9]/";
    $resultado = substr($params->direccion, 0, 1);
    if (!preg_match($docSint, $resultado)) {
        $mensaje = "Error, La dirección debe empezar con un caracter alfanumerico";
        $estado = 301;
    }
    if (empty($tipoDoc)) {
        $mensaje = "Error, no se suministró el tipo de documento";
        $estado = 101;
        $response = new wsResponse($estado, $mensaje);
        return;
    }
    if ($documento == null) {
        $mensaje = "Error, no se suministró el documento ";
        $estado = 102;
        $response = new wsResponse($estado, $mensaje);
        return;
    }
    if ($nombres == null) {
        $mensaje = "Error, no se suministró el nombre";
        $estado = 103;
        $response = new wsResponse($estado, $mensaje);
        return;
    }
    if ($apellidos == null) {
        $mensaje = "Error, no se suministró el apellido ";
        $estado = 104;
        $response = new wsResponse($estado, $mensaje);
        return;
    }
    if ($municipio == null) {
        $mensaje = "Error, no se suministró el código DANE del municipio";
        $estado = 107;
        $response = new wsResponse($estado, $mensaje);
        return;
    }
    if ($direccion == null) {
        $mensaje = "Error, no se suministró la dirección";
        $estado = 108;
        $response = new wsResponse($estado, $mensaje);
        return;
    }
    if ($email == null) {
        $mensaje = "Error, no se suministró el correo electronico ";
        $estado = 109;
        $response = new wsResponse($estado, $mensaje);
        return;
    }
    if ($fechaCert == null) {
        $mensaje = "Error, no se suministró la fecha de inicio del certificado";
        $estado = 110;
        $response = new wsResponse($estado, $mensaje);
        return;
    }
    if ($formato == null) {
        $mensaje = "Error, no se suministró el formato del certificado";
        $estado = 112;
        $response = new wsResponse($estado, $mensaje);
        return;
    }
    if ($tipoCert == null) {
        $mensaje = "Error, no se suministró el tipo de certificado";
        $estado = 114;
        $response = new wsResponse($estado, $mensaje);
        return;
    }
    if ($celular == null) {
        $mensaje = "Error, no se suministró el celular";
        $estado = 119;
        $response = new wsResponse($estado, $mensaje);
        return;
    }


    /*
     * Cuándo no se suministra la vigencia del certificado se define
     * por defecto a 1 año (365 días)
     */
    if ($vigenciaCert == null) {
        $vigenciaCert = 3; // Corresponde a un año
        $b_vigencia = true;
    } else {
        $b_vigencia = true;
    }

    $Sintaxis = '/^(([^<>()\[\]\\.,;:\sáéíóúÁÉÍÓÚñÑ@"]+(\.[^<>()\[\]\\.,;:\sáéíóúÁÉÍÓÚñÑ@"]+)*)|(".+"))@((\[[0-9]{1,3}\.[0-9]{1,3}\.[0-9]{1,3}\.[0-9]{1,3}])|(([a-zA-Z\-0-9]+\.)+[a-zA-Z]{2,}))$/';

    if (preg_match($Sintaxis, $email)) {
        $b_email = true;
    }
    if (preg_match($Sintaxis, $emailEnt)) {
        $b_emailEnt = true;
    }
    // Limpieza de campos con caracteres especiales
    $email = str_replace($char_invalid, "", $email);
    $fechaCert = str_replace($char_invalid, "", $fechaCert);
    $direccion = str_replace($char_invalid, "", $direccion);
    $testigo = str_replace($char_invalid, "", $testigo);
    $foto = str_replace($char_invalid, "", $foto);

    $sqlMun = sprintf("SELECT * FROM municipios WHERE id_municipio= %d;", $municipio);
    $resultadoMun = pg_query($conexion, $sqlMun);
    if (pg_num_rows($resultadoMun) > 0) {
        $b_municipio = true;
    }


    $sqlDoc = sprintf("select * from tipodocumento where tipo_documento = %d;", $tipoDoc);
    $resultadoDoc = pg_query($conexion, $sqlDoc);
    if (pg_num_rows($resultadoDoc) > 0) {
        $b_tipoDoc = true;
    }

    $sqlForma = sprintf("SELECT * FROM formaentregacertificado WHERE id_formaentregacertificado= %d;", $formato);
    $resultadoForma = pg_query($conexion, $sqlForma);
    if (pg_num_rows($resultadoForma) == 0) {
        $mensaje = "Error, la forma de entrega es incorrecta";
        $estado = 322;
    }
    // Validación de fecha del certificado
    $new_date = strtotime($fechaCert);
    if ($new_date == false) {
        $mensaje = "La fecha del Certificado es obligatoria";
        $estado = 320;
        $b_fecha = true;
        $response = new wsResponse($estado, $mensaje);
        return;
    }
    $sql = sprintf("SELECT valor FROM variablesentorno WHERE id_variable=22;");
    $resultado = pg_query($conexion, $sql);
    while ($row = pg_fetch_row($resultado)) {
        $dias_futuros = $row[0];
    }
    $actual_date = strtotime(date("Y-m-d"));
    $tmpDate = strtotime("+$dias_futuros days");
    if (!$b_fecha && $new_date > $tmpDate) {
        $mensaje = "1- Verifique el campo fecha - la fecha del certificado debe ser menor o igual a la fecha: " . date("Y-m-d", $actual_date) . " y " . date("Y-m-d", $tmpDate);
        $estado = 320;
        $b_fecha = true;
        $response = new wsResponse($estado, $mensaje);
        return;
    }
    if (!$b_fecha && $new_date < $actual_date) {
        $mensaje = "2- Verifique el campo fecha - la fecha del certificado debe ser mayor o igual a la fecha actual: " . date("Y-m-d", $new_date) . " y " . date("Y-m-d", $actual_date);
        $estado = 320;
        $b_fecha = true;
        $response = new wsResponse($estado, $mensaje);
        return;
    }
    $new_date = date("Y-m-d", $new_date);
    if ($b_tipoDoc === false) {
        $mensaje = "Error, el valor del tipo de documento es incorrecto";
        $estado = 302;
        $response = new wsResponse($estado, $mensaje);
        return;
    }
    if ($b_vigencia === false) {
        $mensaje = "Error, el valor para la vigencia es incorrecto";
        $estado = 303;
        $response = new wsResponse($estado, $mensaje);
        return;
    }
    if ($b_formato === false) {
        $mensaje = "Error, el valor para el formato es incorrecto";
        $estado = 304;
        $response = new wsResponse($estado, $mensaje);
        return;
    }
    if (strlen($documento) > 20) {
        $mensaje = "Error, el máximo de caracteres en el documento es de 20";
        $estado = 305;
        $response = new wsResponse($estado, $mensaje);
        return;
    }
    if (strlen($nombres) > 70) {
        $mensaje = "Error, el máximo de caracteres en el nombre  es de 70";
        $estado = 306;
        $response = new wsResponse($estado, $mensaje);
        return;
    }
    if (strlen($razonsocial) > 255) {
        $mensaje = "Error, el máximo de caracteres en la razon social es de 255";
        $estado = 321;
        $response = new wsResponse($estado, $mensaje);
        return;
    }
    if (strlen($apellidos) > 70) {
        $mensaje = "Error, el máximo de caracteres en el apellido es de 70";
        $estado = 307;
        $response = new wsResponse($estado, $mensaje);
        return;
    }

    if (strlen($direccion) > 200) {
        $mensaje = "Error, el máximo de caracteres en la dirección es de 200";
        $estado = 308;
        $response = new wsResponse($estado, $mensaje);
        return;
    }
    if (strlen($email) > 70) {
        $mensaje = "Error, el máximo de caracteres en el email es de 70";
        $estado = 309;
        $response = new wsResponse($estado, $mensaje);
        return;
    }
    if ($telefono != null) {
        if (strlen($telefono) > 70) {
            $mensaje = "Error, el máximo de caracteres en el teléfono es de 70";
            $estado = 310;
            $response = new wsResponse($estado, $mensaje);
            return;
        } else if (ctype_digit($telefono) === false) {
            $mensaje = "Verifique el dato telefono - Solo se admiten caracteres numéricos";
            $estado = 316;
        }
    }
    if ($ocupacion != null) {
        if (strlen($ocupacion) > 255) {
            $mensaje = "Error, el máximo de caracteres en la ocupación es de 255";
            $estado = 118;
            $response = new wsResponse($estado, $mensaje);
            return;
        }
    }
    if ($cargo != null) {
        if (strlen($cargo) > 255) {
            $mensaje = "Error, el máximo de caracteres en el cargo es de 255";
            $estado = 311;
            $response = new wsResponse($estado, $mensaje);
            return;
        }
    }
    if (ctype_alnum($documento) === false) {
        $mensaje = "Verifique el dato documento - Solo se admiten caracteres alfa numéricos";
        $estado = 312;
        $response = new wsResponse($estado, $mensaje);
        return;
    }
    if (ctype_alpha(str_replace($char_valid, '', $nombres)) === false) {
        $mensaje = "Verifique el dato nombre - Solo se admiten caracteres alfabéticos";
        $estado = 313;
        $response = new wsResponse($estado, $mensaje);
        return;
    }

    if (ctype_alpha(str_replace($char_valid, '', $apellidos)) === false) {
        $mensaje = "Verifique el dato apellidos - Solo se admiten caracteres alfabéticos";
        $estado = 314;
        $response = new wsResponse($estado, $mensaje);
        return;
    }

    if ($b_municipio === false) {
        $mensaje = "Error, el municipio no se encuentra registrado";
        $estado = 315;
        $response = new wsResponse($estado, $mensaje);
        return;
    }
    if (ctype_digit(str_replace($char_valid, '', $celular)) === false) {
        $mensaje = "Verifique el dato celular - Solo se admiten caracteres numéricos";
        $estado = 316;
        $response = new wsResponse($estado, $mensaje);
        return;
    }
    if ($ocupacion != null) {
        if (strlen($ocupacion) > 255) {
            $mensaje = "Error, el máximo de caracteres en la ocupación es de 255";
            $estado = 317;
            $response = new wsResponse($estado, $mensaje);
            return;
        }
    }
    if ($b_email === false) {
        $mensaje = "Verifique el campo email - Formato incorrecto";
        $estado = 319;
        $response = new wsResponse($estado, $mensaje);
        return;
    }

    $certificadoEmpresa = 0;

    if ($this->idpersonanatural == $tipoCert) {
        $certificadoEmpresa = 1;
    }

    if (in_array($this->ra, $this->ConvenioSinDuplicado)) { // EMSG  2023-09-12
        // Se anexa validacion para no registrar solicitudes duplicadas (Convenios configurados en config.ini) cedula, tipocertificado, vigencia , formato  
        $sql_prev = "select rs.ra_solicitud from  rapersona rp join rapersonasolicitud rps using(id_persona) join rasolicitudes rs using(ra_solicitud) 
        where rp.tipo_documento =  $tipoDoc and id_documento  = '{$documento}' and  rs.id_tipocertificado = $tipoCert and rs.id_vigencia = $vigenciaCert
        and  rs.id_formaentregacertificado  =  $formato and rs.id_estado = 1 ";
        $x_sql_prev = pg_query($conexion, $sql_prev);
        if (pg_num_rows($x_sql_prev) > 0) {
            $mensaje = "Error, Actualmente posee una solicitud en estado de espera. Favor validar.";
            $estado = 321;
            $response = new wsResponse($estado, $mensaje);
            return;
        }
    }

    // Si existe error retorna en el estado y mensaje.
    if ($mensaje != null) {
        $detalle = pg_escape_string($conexion, $mensaje);
        $sql = sprintf("INSERT INTO auditoriaprincipal(id_proceso,fecha,id_funcionario,detalle) VALUES (2,current_timestamp,%d,'%s');", $this->idFunc, $detalle);
        $rsAud = pg_query($conexion, $sql);
        if ($rsAud) {
            AppLog("Ingreso y elimino todo CVE");
        }
        $response = new wsResponse($estado, $mensaje);
    } else {
        switch ($certificadoEmpresa) {
            case 1:
                $sql = sprintf("SELECT tiposervicio.nombre, certificados.fin_vigencia FROM certificados
                    JOIN personasolicitud USING(id_solicitud)
                    JOIN solicitudes USING(id_solicitud)
                    JOIN unionsolicitudesra USING(id_solicitud)
                    JOIN rasolicitudes USING(ra_solicitud)
                    JOIN funcionariora ON(rasolicitudes.id_funcionario = funcionariora.id_funcionario)
                    JOIN tiposervicio ON(rasolicitudes.id_tipocertificado = tiposervicio.id_tipocertificado)
                    WHERE certificados.id_estado = 1
                    AND fecha_revocacion IS NULL
                    AND fin_vigencia > current_timestamp
                    AND personasolicitud.id_documento = '%s' AND personasolicitud.tipo_documento = %d
                    AND rasolicitudes.id_tipocertificado = %d and id_ra = %d
                    ;", $documento, $tipoDoc, $documentoEnt, $tipoDocEnt, $tipoCert, $this->ra);
                $result = pg_query($conexion, $sql);
                $tipo = "";
                $fin_vigencia = "";
                if (pg_num_rows($result) > 0) {
                    while ($row = pg_fetch_object($result)) {
                        $fecha = explode(" ", $row->fin_vigencia);
                        if (((strtotime($fecha[0]) - strtotime(date('Y-m-d'))) / (60 * 60 * 24)) > 30) {
                            $fin_vigencia = $row->fin_vigencia;
                            $tipo = $row->nombre;
                            $b_certificado = true;
                            break;
                        }
                    }
                }
                if ($b_certificado === true) {
                    $mensaje = "Se rechaza la solicitud porque se encontro certificado vigente que caduca el " . $fin_vigencia . " como "
                        . "$tipo para $nombres $apellidos $documento ";
                    $estado = 318;
                    break;
                }
                $pass = rand('10000000', '99999999');
                $password = AesCtr::encrypt($pass, $params->documento, 256);
                if ($params->pin != "") {
                    $pin_descarga = $params->pin;
                    if (validarPin($pin_descarga)) {
                        $mensaje = "Error, El pin debe contener letras, números y alguno de los siguientes caracteres especiales: @!%*?+\-&# y tener 10 caracteres.";
                        $estado = 403;
                        break;
                    }
                    $cadena = $params->documento . $params->tipoDoc;
                    $newidalias = sha1($cadena . $pin_descarga);
                    $sqlToken = "select * from tokenalias where id_alias = '$newidalias'";
                    $resultToken = pg_query($conexion, $sqlToken);
                    if (pg_num_rows($resultToken) > 0) {
                        $mensaje = "Pin no valido, ya se encuentra registrado";
                        $estado = 708;
                        break;
                    }
                } else {
                    $pin_descarga = rand('10000000', '99999999');
                }
                $sql_sol = sprintf("INSERT INTO rasolicitudes(fecha_registro,
                        id_funcionario,id_tipocertificado,id_estado,id_formaentregacertificado,
                        id_vigencia,fecha_iniciovigencia,password,
                        ca_emite,verificacion,puntaje_identificacion,aprobar_emision) VALUES(current_timestamp,%d,%d,1,%d,%d,
                        '%s','%s','%s','%s',%d,'%s') RETURNING ra_solicitud ;",
                    $this->idFunc,
                    $tipoCert,
                    $formato,
                    $vigenciaCert,
                    $new_date,
                    $password,
                    $this->caEmite
                    ,
                    $dataVerificacion,
                    $puntaje,
                    $aprobarEmision
                );
                $resultado_sol = pg_query($conexion, $sql_sol);
                if ($resultado_sol == FALSE) {
                    $mensaje = "Error, hubo un problema en el registro de la solicitud ";
                    $estado = 403;
                    break;
                }
                $row_result = pg_fetch_object($resultado_sol);
                $ra_solicitud = $row_result->ra_solicitud;

                /* Registro de soportes .zip en directorio de la NAS  ESMG 2024-04-15*/
                $result_saveZip = updImagenDigitalRa($ra_solicitud, $DocAdj, $this->RUTA_SOPORTES, $conexion);

                if ($result_saveZip === false) {
                    $mensaje = "Error, hubo un problema en la actualización del path archivo .zip  ";
                    $estado = 422;
                    break;
                }
                /* */

                $sql = "select login, nombre, id_ra from funcionariora join ra using(id_ra) where id_funcionario = $this->idFunc";
                $res = pg_query($conexion, $sql);
                while ($row = pg_fetch_row($res)) {
                    $login = $row[0];
                    $nombre = $row[1];
                    $id_ra = $row[2];
                }
                $mensajetoken = "";
                $token_andesid = $params->token_andesid;
                $sql_tok = "INSERT INTO rasolicitud_tokenandesid VALUES($ra_solicitud ,'$token_andesid')";
                $resultado = pg_query($conexion, $sql_tok);
                if ($resultado == FALSE) {
                    $mensaje = "Error, hubo un problema en el del token de validacion $sql_tok";
                    $estado = 405;
                    break;
                }
                if ($token_andesid != "") {
                    $mensajetoken = " con token " . $token_andesid;
                }

                $descripcion = "Se registra solicitud  $ra_solicitud tramitada, usuario de webservice $this->idFunc $login de entidad $nombre $id_ra $mensajetoken";
                $sql_sol = "INSERT INTO auditoriasolicitud(id_evento, fecha,id_funcionario,descripcion, ra_solicitud) VALUES(39,current_timestamp,$this->idFunc, '" . pg_escape_string($descripcion) . "', $ra_solicitud);";
                $resultado = pg_query($conexion, $sql_sol);
                if ($resultado == FALSE) {
                    $mensaje = "Error, hubo un problema en el registro de la auditoria de la rasolicitud";
                    $estado = 405;
                    break;
                }
                $sql_per = sprintf("INSERT INTO rapersona(tipo_documento,
                        id_documento,nombres,apellidos,id_municipio,
                        domicilio,telefono,email,celular_contacto,
                        ocupacion,foto,huella,verificacion_identidad,pin_descarga)
                        VALUES(%d,'%s','%s','%s',%d,'%s',%d,'%s',%d,'%s','%s',null,'%s', MD5('%s')) RETURNING id_persona ;", $tipoDoc, $documento, pg_escape_string($nombres), pg_escape_string($apellidos), $municipio, pg_escape_string($direccion), $telefono, $email, $celular, pg_escape_string($ocupacion), $foto, $testigo, $pin_descarga);
                $resultado_per = pg_query($conexion, $sql_per);
                if ($resultado_per == FALSE) {
                    AppLog("fallo sql " . $sql . " ---" . pg_last_error($conexion));
                    $mensaje = "Error, hubo un problema en el registro de la persona";
                    $estado = 404;
                    break;
                }
                $row_result = pg_fetch_object($resultado_per);
                $id_persona = $row_result->id_persona;



                $sql_per_sol = sprintf("INSERT INTO rapersonasolicitud VALUES(%d,%d);", $ra_solicitud, $id_persona);
                $resultado_per_sol = pg_query($conexion, $sql_per_sol);
                if ($resultado_per_sol == FALSE) {
                    $mensaje = "Error, hubo un problema en el registro de la persona_solicitud";
                    $estado = 405;
                    break;
                }

                $cadena = $documento . $tipoDoc;
                $secret_key = $pin_descarga;
                $id_alias = sha1($cadena . $secret_key);
                $alias = $this->idFunc . sha1($ra_solicitud);
                $secret = AesCtr::encrypt($pin_descarga, $params->documento, 256);
                if (!empty($pkcs10)) {
                    if ($formato !== 3) {
                        $mensaje = "Error, el formato de entrega solicitado no requiere un pkcs10";
                        $estado = 431;
                        break;
                    }

                    // Envío de petición csr a través del Cliente.
                    // $comando = "echo -n '$pkcs10' | openssl req -noout -modulus 2>&1";
                    // exec($comando, $output, $return_var);
                    // $output = explode("=", $output[0]);


                    // if ($return_var !== 0) {
                    //     $mensaje = "El pkcs10 digitado no es una solicitud de certificado";
                    //     $estado = 406;
                    //     break;
                    // } else {
                    //     $sqlCertificado = "select * from solicitudes where modulo_llave ='$output[1]';";
                    //     $rsCertificado = pg_query($conexion, $sqlCertificado);
                    //     if (pg_num_rows($rsCertificado) > 0) {
                    //         $mensaje = "La solicitud de certificado ya esta siendo utilizada, por favor actualice el csr";
                    //         $estado = 406;
                    //         break;
                    //     }
                    // }

                    //IMPLEMENTADO AMQM

                    $comando = "echo -n '$pkcs10' | openssl req -noout -text 2>&1";
                    exec($comando, $output, $return_var);

                    if ($return_var !== 0) {
                        $mensaje = "El pkcs10 digitado no es una solicitud de certificado";
                        $estado = 406;
                        break;
                    }

                    $outputText = implode("\n", $output);

                    $modulo = '';
                    // $mensaje = "prueba de modulo".$outputText;
                    //         $estado = 406;
                    //         break;
                    if (strpos($outputText, "rsaEncryption") !== false) {
                        // PARA RSA
                        $comandoModulus = "echo -n '$pkcs10' | openssl req -noout -modulus 2>&1";

			syslog(LOG_INFO,"el PKCS10 es: $pcks10");

                        exec($comandoModulus, $outputModulus, $returnModulus);

                        if ($returnModulus !== 0 || empty($outputModulus)) {
                            $mensaje = "No se pudo obtener el modulus de la solicitud RSA";
                            $estado = 406;
                            break;
                        }

                        $outputParts = explode("=", $outputModulus[0]);
                        if (count($outputParts) < 2) {
                            $mensaje = "Formato inválido al obtener el modulus de la solicitud RSA";
                            $estado = 406;
                            break;
                        }

                        $modulo = trim($outputParts[1]);

                    } elseif (strpos($outputText, "id-ecPublicKey") !== false) {
                        // PARA ECDSA
                        if (preg_match('/ASN1 OID: ([^\n]+)/', $outputText, $matches)) {
                            $curveName = trim($matches[1]);

                            if (!in_array($curveName, ['prime256v1', 'secp384r1', 'secp521r1','secp256k1'])) {
                                $mensaje = "La curva utilizada no está permitida: $curveName";
                                $estado = 406;
                                break;
                            }

                           
                            $modulo = hash('sha256', $pkcs10);

                        } else {
                            $mensaje = "No se pudo detectar la curva EC usada en el CSR";
                            $estado = 406;
                            break;
                        }
                    } else {
                        $mensaje = "El tipo de clave en el CSR no está soportado (debe ser RSA o EC)";
                        $estado = 406;
                        break;
                    }

                    $sqlCertificado = "SELECT * FROM solicitudes WHERE modulo_llave = '$modulo';";

		    syslog(LOG_INFO,"La consulta es: $sqlCertificado");

                    $rsCertificado = pg_query($conexion, $sqlCertificado);

                    if (pg_num_rows($rsCertificado) > 0) {
                        $mensaje = "La solicitud de certificado ya está siendo utilizada, por favor actualice el CSR";
                        $estado = 406;
                        break;
                    }

                    //FIN IMPLEMENTADO AMQM

                    $csr = $pkcs10;


                    $sql_upd = sprintf("UPDATE rasolicitudes SET pkcs10 = '%s' WHERE ra_solicitud = %d ", $csr, $ra_solicitud);

                    $resultado_upd = pg_query($conexion, $sql_upd);
                    if ($resultado_upd == FALSE) {
                        $mensaje = "Error, hubo un problema en la actualización del pkcs10";
                        $estado = 422;
                        break;
                    }

                } else {
                    if ($params->formato == 3) {
                        if (empty($params->pkcs10)) {
                            $mensaje = "Error, el formato de entrega solicitado requiere un pkcs10";
                            $estado = 431;
                            break;
                        }
                    }

                    $insertStore = "insert into storepin (ra_solicitud, alias, pin_descarga, hash_pin) values ($ra_solicitud, '$alias', '$secret', encode(digest('$secret_key', 'sha256'), 'hex'))";
                    $resultadoStore = pg_query($conexion, $insertStore);
                    if ($resultadoStore == false) {
                        $mensaje = "Error, hubo un problema almacenando el pin de descarga";
                        $estado = 412;
                        break;
                    }

                    $sql = sprintf("INSERT INTO tokenalias(id_alias,alias, almacenamiento, kek_alias) VALUES('%s','%s', %d, '%s') RETURNING id ;", $id_alias, $alias, 2, $this->KEKALIAS);
                    $resultado = pg_query($conexion, $sql);
                    if ($resultado == FALSE) {
                        AppLog("||" . $sql . " --> " . pg_last_error($conexion));
                        $mensaje = "Error, hubo un problema en el registro del token";
                        $estado = 413;
                        break;
                    }
                    $row_result = pg_fetch_object($resultado);
                    $id_token = $row_result->id;
                    AppLog("llego hasta aca " . $alias . " " . $ra_solicitud);
                    list($estado, $output, $hash) = $this->generateKeys(array($alias, $secret_key, $ra_solicitud, $this->KEKALIAS));
                    if ($estado != 0 || $output == "" || $hash == "") {
                        $mensaje = "Error, hubo un problema en la generación de las llaves y en la generación de la petición CSR";
                        $estado = 414;
                        break;
                    } else {
                        // Generación de la petición csr a través del Token
                        $this->keysgen = true;
                        $csr = $output; //file_get_contents($this->dir_tmp.'csr/'.$id_dnsolicitud . '_' . $peticion_csr . '.csr');
                        $peticion_csr_solicitud = $id_dnsolicitud . '_' . $peticion_csr;
                        $sql_llave = sprintf("UPDATE tokenalias SET llave_privada = '%s' WHERE alias = '%s'; ", $hash, $alias);
                        $resultado_llave = pg_query($conexion, $sql_llave);
                        if ($resultado_llave == FALSE) {
                            $mensaje = "Error, hubo un problema almanando la llave privada";
                            $estado = 422;
                            break;
                        }
                        $sql_upd = sprintf("UPDATE rasolicitudes SET pkcs10 = '%s' WHERE ra_solicitud = %d ", $csr, $ra_solicitud);

                        $resultado_upd = pg_query($conexion, $sql_upd);
                        if ($resultado_upd == FALSE) {
                            $mensaje = "Error, hubo un problema en la actualización del pkcs10";
                            $estado = 422;
                            break;
                        }
                    }
                }
                break;
            default:
                $estado = 202;
                $mensaje = "El tipo de certificado aún no está definido";
                break;
        }


        if ($mensaje != null) {
            pg_query($conexion, "ROLLBACK;");

            $d = delImagenDigitalRa($ra_solicitud, $this->RUTA_SOPORTES);

            $detalle = pg_escape_string($conexion, $mensaje);
            $sql = sprintf("INSERT INTO auditoriaprincipal(id_proceso,fecha,id_funcionario,detalle) VALUES (2,current_timestamp,%d,'%s');", $this->idFunc, $detalle);
            $rsAud = pg_query($conexion, $sql);
            if ($rsAud !== false) {
                AppLog("Ingreso y elimino todo CPN");
            }
        } else {

            pg_query($conexion, "COMMIT;");
            $mensaje = $ra_solicitud;
	    syslog(LOG_INFO,"LLega aqui enemision automática");
            if ($this->EMISION_AUTOMATICA == "t") {
                $params = array(
                    "tipoDoc" => $tipoDoc,
                    "documento" => $documento,
                    "rasolicitud" => $ra_solicitud,
                    "tipoCert" => $tipoCert,
                    "id_vigencia" => $vigenciaCert,
                    "iFormaEntrega" => $formato,
                    "observaciones" => "Emision Automatica por convenio $this->NOMBRE_RA",
                    "notas_estudio" => null
                );
                $o = $this->EmitirCertificado(json_encode($params));
                $estado = $o->estado;
                $mensaje = $o->mensaje;
            } else {
                /*
                NOTIFICACIÓN EMAIL A SUSCRIPTOR PARA PROCESO DE VALIDACION DE 
                */

                $sqlConvenioValidacion = "select solicitar_validacion_identidad from ra where id_ra=" . $id_ra;
                $result = pg_query($conexion, $sqlConvenioValidacion);
                $row = pg_fetch_assoc($result);
                $campo = $row['solicitar_validacion_identidad'];

                if ($campo == 't') {
                    $insertSolicitarValidacion = "insert into convenio_envio_solicitud_validacion (ra_solicitud, id_ra, estado_envio) values ($ra_solicitud, $id_ra, 0)";
                    $resultado = pg_query($conexion, $sql_sol);
                    $resultadoSolicitarValidacion = pg_query($conexion, $insertSolicitarValidacion);
                    pg_query($conexion, "COMMIT;");
                }


            }
        }
    }
    $response = new wsResponse($estado, $mensaje);
} else {
    $response = new wsResponse(201, "No se ha podido validar la autenticación para hacer uso del servicio");
}