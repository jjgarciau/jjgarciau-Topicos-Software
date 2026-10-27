# randomquotes – Microservicio Express desplegado en AWS

Microservicio Express que responde con una frase aleatoria y el hostname del contenedor que atendió la solicitud:

```
See rejection as redirection. - Container Id: ip-10-0-1-23.ec2.internal
```

## Arquitectura

```
GitHub ──► GitHub Actions ──► Amazon ECR ──► Amazon ECS (Fargate, 4 tareas)
                                                       ▲
Navegador / curl ──► Application Load Balancer ────────┘
```

| Recurso AWS            | Nombre                                  |
| ---------------------- | --------------------------------------- |
| Repositorio ECR        | `randomquotes-juan-garcia`                          |
| Clúster ECS            | `randomquotes-cluster-juan-garcia`                  |
| Task Definition        | `randomquotes-task-juan-garcia` (0.25 vCPU / 0.5 GB) |
| Servicio ECS           | `randomquotes-service-juan-garcia` (desired = 4)    |
| Load Balancer          | `randomquotes-alb-juan-garcia` (HTTP :80)           |
| Target Group           | `randomquotes-tg-juan-garcia` (tipo IP, `/`)   |
| Security Groups        | `randomquotes-alb-sg-juan-garcia`, `randomquotes-task-sg-juan-garcia` |
| Logs                   | CloudWatch `/ecs/randomquotes-juan-garcia`          |
| Rol de GitHub Actions  | `GitHubActionsRandomquotesRole-juan-garcia` (OIDC)  |

## Archivos

- `index.js`: servidor Express en el puerto 80.
- `Dockerfile`: imagen `node:22-alpine` con `npm ci --omit=dev`.
- `aws/task-definition.json`: Task Definition de ECS (cuenta `102301143601`, región `us-east-1`).
- `aws/github-oidc-trust-policy.json` y `aws/github-actions-permissions-policy.json`: políticas del rol IAM que usa GitHub Actions.
- `../.github/workflows/deploy-randomquotes.yml`: en cada push a `main` que toque `randomquotes/`, construye la imagen, la sube a ECR y fuerza un nuevo despliegue del servicio ECS.

## Ejecución local

```bash
npm install
PORT=3000 node index.js          # http://localhost:3000
# o con Docker
docker build -t randomquotes .
docker run --rm -p 8080:80 randomquotes   # http://localhost:8080
```

## Evidencias

> Agregar capturas en `docs/` y enlazarlas aquí.

1. Imagen `latest` publicada en ECR.
2. Servicio ECS con 4 tareas RUNNING y 4 destinos healthy en el Target Group.
3. Balanceo: salida de `curl` al DNS del ALB mostrando al menos 3 Container Id distintos.
4. Recuperación: tarea detenida manualmente y eventos del servicio mostrando la tarea de reemplazo.
5. Ejecución exitosa del workflow en GitHub Actions y la frase modificada visible en el ALB.

## Conclusiones

- **Papel del ALB:** es el único punto de entrada público. Recibe las solicitudes HTTP y las reparte entre las tareas registradas en el Target Group, enviando tráfico solo a las que pasan el health check. Las tareas no están expuestas directamente: su Security Group solo acepta tráfico desde el ALB.
- **¿Por qué se crea una nueva tarea al detener una?** El servicio ECS compara continuamente las tareas en ejecución con el *desired count* (4). Si hay menos, programa tareas de reemplazo. Quien mantiene la cantidad deseada es el **ECS Service** (su scheduler).
- **Efecto del health check:** el ALB deja de enviar tráfico a una tarea que no responde en `/`, y ECS puede reemplazar las tareas marcadas como no saludables. Mientras la tarea nueva arranca, el tráfico va a las tareas sanas, así el servicio no se interrumpe.
- **CI/CD:** GitHub Actions se autentica en AWS con OIDC (credenciales temporales, sin access keys guardadas en GitHub), publica la imagen en ECR y fuerza un despliegue rolling del servicio.
