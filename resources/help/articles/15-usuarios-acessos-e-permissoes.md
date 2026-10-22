---
id: usuarios-acessos-e-permissoes
title: Usuários, Acessos, Senhas e Permissões do Gestor
subtitle: Segurança de acesso e escopo
category: configuracao
profiles:
  - admin
  - manager
  - employee
  - auditor
icon: users-round
keywords: usuarios, acesso, senha, gerente, gestor, rh, auditor, permissao, setores, perfil, redefinir senha, administrador, login, bloqueado
order: 4
featured: false
summary: Saiba como criar usuários, definir perfis, recuperar acesso e compreender quais informações o gestor, o RH e o auditor podem acessar.
---

## 1. Usuário de acesso e cadastro funcional são coisas diferentes

Um **usuário** define credenciais e papel no sistema. Um **funcionário/servidor** define CPF, lotação, vínculo, carga horária e escala. O mesmo cadastro funcional precisa estar corretamente associado ao usuário que registra o ponto.

Não crie um usuário extra somente para trocar de horário ou atuar em outra unidade. O suporte completo a vários locais por funcionário ainda é uma evolução planejada.

## 2. Criar ou atualizar um usuário — RH

1. Acesse **Usuários** (`/admin/users`) com conta administrativa.
2. Escolha **Adicionar Usuário** ou localize um existente para editar.
3. Informe nome, e-mail, senha inicial e papel de acesso.
4. Salve e confirme a listagem.
5. No cadastro em **Servidores** (`/admin/employees`), confira o vínculo funcional e o setor principal.

A senha de uma conta existente só deve ser alterada quando o campo de senha for preenchido na edição. Distribua credenciais individualmente por um canal seguro; evite senhas compartilhadas.

### Perfis

| Perfil | Atividades principais |
|---|---|
| Colaborador | Registrar ponto, consultar próprios comprovantes/espelho, solicitar tratamento |
| Gestor | Consultar e tratar jornadas dos servidores dos setores sob sua gestão, aprovar solicitações autorizadas e registrar conferência |
| Administrador/RH | Gerenciar organização, usuários, setores, perfis normativos, políticas, escalas, tratamentos e fechamento |
| Auditor | Consultar os módulos fiscais e evidências conforme suas permissões; não é um administrador operacional |

## 3. Como é determinado o escopo do gestor

Na versão atual, o vínculo entre gestor e setor é configurado em **Setores → Responsável do Setor**. A aplicação utiliza especialmente o **setor de lotação principal** do funcionário para determinar quem pode acessar sua escala, tratamentos e relatórios.

**Exemplo:** servidor lotado na Secretaria de Saúde que realiza plantão no hospital. O gestor hospitalar **ainda não recebe automaticamente** autorização restrita a esse plantão. Essa granularidade não deve ser presumida nem concedida manualmente por compartilhamento de credenciais.

A definição futura de permissões por local/plantão deverá manter acesso limitado ao trabalho efetuado na unidade, preservando o RH como responsável pelo cadastro funcional integral.

## 4. Aprovação, sigilo e segregação de funções

- Gestores não devem aprovar as próprias solicitações de tratamento; a responsabilidade é do RH.
- O RH controla autorização normativa das escalas especiais; gestores não podem homologar normas.
- Documentos de justificativas e atestados devem ser visualizados apenas por perfis autorizados.
- Contas de auditor não devem ser utilizadas para operações rotineiras de RH.

## 5. Perda de acesso e redefinição de senha

1. Confira o endereço oficial e o e-mail de login.
2. Se a senha não funcionar, solicite redefinição ao RH/administrador.
3. O administrador abre **Usuários**, edita o registro e define uma nova senha.
4. Faça login e substitua credenciais provisórias conforme o procedimento institucional.

Evite criar novo usuário para corrigir o acesso: o histórico de ponto está associado ao cadastro original.

## 6. Encerramento de vínculo

Antes de remover uma conta, confirme com o RH o impacto na consulta de comprovantes e na preservação dos registros de frequência. A remoção de acesso não deve apagar registros fiscais nem documentos históricos que precisem ser preservados. Prefira procedimento de desligamento formal à exclusão direta quando existirem marcações e competências fechadas.
