# PontoFácil - Sistema de Controle de Ponto (REP-A)

O **PontoFácil** é um sistema moderno, seguro e antifraude para registro alternativo de ponto (aderente à Portaria 671), focado em garantir a integridade da batida de ponto através de validação dupla: leitura de QR Code físico corporativo aliada à captura de Geolocalização (GPS) do dispositivo do funcionário.

## 🚀 Principais Funcionalidades

- **Batida de Ponto Inteligente (Smart Punch):** Leitura de QR Code e verificação de coordenadas GPS via navegador, validando se o funcionário está no raio permitido da empresa.
- **Validação Anti-Fraude (Server-side):** O horário do ponto é garantido e carimbado unicamente pelo servidor no momento da inserção.
- **Espelho de Ponto (Timesheet):** Dashboard intuitivo com histórico mensal das batidas, separado por funcionário.
- **Ajustes Manuais & Auditoria (RH):** Interface segura para gestores inserirem ou editarem batidas de ponto retroativamente. Todos os ajustes geram, obrigatoriamente, um registro irreversível de trilha de auditoria contendo a justificativa e o histórico das alterações.
- **UX Fluida:** Construído como Single Page Application behavior, contando com transições suaves e loadings visuais que otimizam a experiência.

## 🛠️ Stack Tecnológica

O sistema foi estruturado utilizando tecnologias de ponta do ecossistema TALL:
- **[Laravel 13](https://laravel.com):** Framework PHP backend focado em robustez, elegância e segurança.
- **[Livewire 4](https://livewire.laravel.com):** Componentes dinâmicos *View-Based*, mantendo toda a lógica no PHP sem complicação.
- **[Alpine.js](https://alpinejs.dev):** Manipulação pontual de JavaScript (ex: acionamento do scanner de Câmera e GPS).
- **[Tailwind CSS](https://tailwindcss.com):** Estilização ágil e moderna focada em utilitários.
- **Banco de Dados:** MySQL 8+.

## ⚙️ Implantação (Deploy)

Desenvolvido para máxima compatibilidade, este sistema foi otimizado para deploy em uma infraestrutura **VPS** rodando **CloudPanel** e **Nginx**. É mandatório o uso de certificado SSL (**HTTPS**) em produção, pois apenas assim os navegadores liberam acesso à Câmera (QR Code) e ao Sensor de Localização (GPS).

---

## 🔒 Licença e Direitos Autorais

**ATENÇÃO: Este software NÃO É Open Source (Código Aberto).**

Todos os direitos são reservados. A cópia, distribuição, modificação ou uso comercial deste código-fonte sem autorização prévia e expressa são estritamente proibidos.

Desenvolvido orgulhosamente por **[KL Tecnologia](https://kltecnologia.com)**.
