#!/bin/bash
set -e
BASE="${1:-http://localhost:19090}"
COOKIE=/tmp/install_test_cookies.txt
rm -f "$COOKIE"

curl -s -c "$COOKIE" -b "$COOKIE" "$BASE/install" > /dev/null

echo "=== POST periodico ==="
curl -s -c "$COOKIE" -b "$COOKIE" -o /tmp/step2.html -w "HTTP %{http_code} -> %{redirect_url}\n" \
  -X POST "$BASE/install/periodico" \
  -d "name=Mi Periodico&code=milocal&domain=milocal.test&locale=es&timezone=Europe/Madrid"
grep -c "Identidad visual" /tmp/step2.html && echo "OK: apariencia page" || (grep "error-msg" /tmp/step2.html; echo "FAIL periodico")

echo "=== POST apariencia ==="
curl -s -c "$COOKIE" -b "$COOKIE" -o /tmp/step3.html -w "HTTP %{http_code}\n" \
  -X POST "$BASE/install/apariencia" \
  -d "tagline=Eslogan&primaryColor=%23ff0000&secondaryColor=%230000ff"
grep -c "Administrador" /tmp/step3.html && echo "OK: admin page" || echo "FAIL apariencia"

echo "=== POST admin ==="
curl -s -c "$COOKIE" -b "$COOKIE" -o /tmp/step4.html -w "HTTP %{http_code}\n" \
  -X POST "$BASE/install/admin" \
  -d "adminName=Victor&adminEmail=victor@test.com&adminPassword=secret1234"
grep -c "Resumen" /tmp/step4.html && echo "OK: resumen page" || echo "FAIL admin"

echo "=== GET resumen + POST finalizar ==="
curl -s -c "$COOKIE" -b "$COOKIE" "$BASE/install/resumen" > /tmp/resumen.html
TOKEN=$(sed -n 's/.*name="_token" value="\([^"]*\)".*/\1/p' /tmp/resumen.html | head -1)
echo "CSRF token length: ${#TOKEN}"
curl -s -c "$COOKIE" -b "$COOKIE" -o /tmp/final.html -w "HTTP %{http_code}\n" \
  -X POST "$BASE/install/finalizar" -d "_token=$TOKEN"
grep -c "Instalación completada" /tmp/final.html && echo "OK: install complete" || (grep -E "flash-error|Error" /tmp/final.html | head -5; echo "FAIL finalize")
