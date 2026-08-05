--
-- PostgreSQL database dump
--

\restrict 6toMfRVpSjNfoxNIu7ISI4RrXtffhN0CDtTkWJNUAW71sKXTDtLc8oe2cfbcgd3

-- Dumped from database version 17.10 (Debian 17.10-0+deb13u1)
-- Dumped by pg_dump version 17.10 (Debian 17.10-0+deb13u1)

SET statement_timeout = 0;
SET lock_timeout = 0;
SET idle_in_transaction_session_timeout = 0;
SET transaction_timeout = 0;
SET client_encoding = 'UTF8';
SET standard_conforming_strings = on;
SELECT pg_catalog.set_config('search_path', '', false);
SET check_function_bodies = false;
SET xmloption = content;
SET client_min_messages = warning;
SET row_security = off;

SET default_tablespace = '';

SET default_table_access_method = heap;

--
-- Name: cache; Type: TABLE; Schema: public; Owner: javier
--

CREATE TABLE public.cache (
    key character varying(255) NOT NULL,
    value text NOT NULL,
    expiration bigint NOT NULL
);


ALTER TABLE public.cache OWNER TO javier;

--
-- Name: cache_locks; Type: TABLE; Schema: public; Owner: javier
--

CREATE TABLE public.cache_locks (
    key character varying(255) NOT NULL,
    owner character varying(255) NOT NULL,
    expiration bigint NOT NULL
);


ALTER TABLE public.cache_locks OWNER TO javier;

--
-- Name: carreras; Type: TABLE; Schema: public; Owner: javier
--

CREATE TABLE public.carreras (
    id bigint NOT NULL,
    facultad_id bigint NOT NULL,
    nombre character varying(255) NOT NULL,
    slug character varying(255) NOT NULL,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone
);


ALTER TABLE public.carreras OWNER TO javier;

--
-- Name: carreras_id_seq; Type: SEQUENCE; Schema: public; Owner: javier
--

CREATE SEQUENCE public.carreras_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


ALTER SEQUENCE public.carreras_id_seq OWNER TO javier;

--
-- Name: carreras_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: javier
--

ALTER SEQUENCE public.carreras_id_seq OWNED BY public.carreras.id;


--
-- Name: categorias; Type: TABLE; Schema: public; Owner: javier
--

CREATE TABLE public.categorias (
    id bigint NOT NULL,
    nombre character varying(255) NOT NULL,
    slug character varying(255) NOT NULL,
    descripcion text,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone
);


ALTER TABLE public.categorias OWNER TO javier;

--
-- Name: categorias_id_seq; Type: SEQUENCE; Schema: public; Owner: javier
--

CREATE SEQUENCE public.categorias_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


ALTER SEQUENCE public.categorias_id_seq OWNER TO javier;

--
-- Name: categorias_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: javier
--

ALTER SEQUENCE public.categorias_id_seq OWNED BY public.categorias.id;


--
-- Name: departamentos; Type: TABLE; Schema: public; Owner: javier
--

CREATE TABLE public.departamentos (
    id bigint NOT NULL,
    facultad_id bigint NOT NULL,
    nombre character varying(255) NOT NULL,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone
);


ALTER TABLE public.departamentos OWNER TO javier;

--
-- Name: departamentos_id_seq; Type: SEQUENCE; Schema: public; Owner: javier
--

CREATE SEQUENCE public.departamentos_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


ALTER SEQUENCE public.departamentos_id_seq OWNER TO javier;

--
-- Name: departamentos_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: javier
--

ALTER SEQUENCE public.departamentos_id_seq OWNED BY public.departamentos.id;


--
-- Name: eventos; Type: TABLE; Schema: public; Owner: javier
--

CREATE TABLE public.eventos (
    id bigint NOT NULL,
    titulo character varying(255) NOT NULL,
    slug character varying(255) NOT NULL,
    descripcion text,
    tipo_evento character varying(255) NOT NULL,
    fecha_inicio timestamp(0) without time zone NOT NULL,
    fecha_fin timestamp(0) without time zone,
    lugar character varying(255),
    organizador_id bigint NOT NULL,
    facultad_id bigint,
    carrera_id bigint,
    categoria_id bigint NOT NULL,
    imagen_principal character varying(255),
    banner character varying(255),
    miniatura character varying(255),
    estado character varying(255) DEFAULT 'programado'::character varying NOT NULL,
    url_transmision character varying(255),
    duracion_minutos integer,
    responsable_streaming_id bigint,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone,
    deleted_at timestamp(0) without time zone,
    CONSTRAINT eventos_estado_check CHECK (((estado)::text = ANY ((ARRAY['programado'::character varying, 'en_vivo'::character varying, 'finalizado'::character varying, 'cancelado'::character varying])::text[])))
);


ALTER TABLE public.eventos OWNER TO javier;

--
-- Name: eventos_id_seq; Type: SEQUENCE; Schema: public; Owner: javier
--

CREATE SEQUENCE public.eventos_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


ALTER SEQUENCE public.eventos_id_seq OWNER TO javier;

--
-- Name: eventos_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: javier
--

ALTER SEQUENCE public.eventos_id_seq OWNED BY public.eventos.id;


--
-- Name: facultades; Type: TABLE; Schema: public; Owner: javier
--

CREATE TABLE public.facultades (
    id bigint NOT NULL,
    nombre character varying(255) NOT NULL,
    slug character varying(255) NOT NULL,
    descripcion text,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone
);


ALTER TABLE public.facultades OWNER TO javier;

--
-- Name: facultades_id_seq; Type: SEQUENCE; Schema: public; Owner: javier
--

CREATE SEQUENCE public.facultades_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


ALTER SEQUENCE public.facultades_id_seq OWNER TO javier;

--
-- Name: facultades_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: javier
--

ALTER SEQUENCE public.facultades_id_seq OWNED BY public.facultades.id;


--
-- Name: failed_jobs; Type: TABLE; Schema: public; Owner: javier
--

CREATE TABLE public.failed_jobs (
    id bigint NOT NULL,
    uuid character varying(255) NOT NULL,
    connection character varying(255) NOT NULL,
    queue character varying(255) NOT NULL,
    payload text NOT NULL,
    exception text NOT NULL,
    failed_at timestamp(0) without time zone DEFAULT CURRENT_TIMESTAMP NOT NULL
);


ALTER TABLE public.failed_jobs OWNER TO javier;

--
-- Name: failed_jobs_id_seq; Type: SEQUENCE; Schema: public; Owner: javier
--

CREATE SEQUENCE public.failed_jobs_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


ALTER SEQUENCE public.failed_jobs_id_seq OWNER TO javier;

--
-- Name: failed_jobs_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: javier
--

ALTER SEQUENCE public.failed_jobs_id_seq OWNED BY public.failed_jobs.id;


--
-- Name: job_batches; Type: TABLE; Schema: public; Owner: javier
--

CREATE TABLE public.job_batches (
    id character varying(255) NOT NULL,
    name character varying(255) NOT NULL,
    total_jobs integer NOT NULL,
    pending_jobs integer NOT NULL,
    failed_jobs integer NOT NULL,
    failed_job_ids text NOT NULL,
    options text,
    cancelled_at integer,
    created_at integer NOT NULL,
    finished_at integer
);


ALTER TABLE public.job_batches OWNER TO javier;

--
-- Name: jobs; Type: TABLE; Schema: public; Owner: javier
--

CREATE TABLE public.jobs (
    id bigint NOT NULL,
    queue character varying(255) NOT NULL,
    payload text NOT NULL,
    attempts smallint NOT NULL,
    reserved_at integer,
    available_at integer NOT NULL,
    created_at integer NOT NULL
);


ALTER TABLE public.jobs OWNER TO javier;

--
-- Name: jobs_id_seq; Type: SEQUENCE; Schema: public; Owner: javier
--

CREATE SEQUENCE public.jobs_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


ALTER SEQUENCE public.jobs_id_seq OWNER TO javier;

--
-- Name: jobs_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: javier
--

ALTER SEQUENCE public.jobs_id_seq OWNED BY public.jobs.id;


--
-- Name: migrations; Type: TABLE; Schema: public; Owner: javier
--

CREATE TABLE public.migrations (
    id integer NOT NULL,
    migration character varying(255) NOT NULL,
    batch integer NOT NULL
);


ALTER TABLE public.migrations OWNER TO javier;

--
-- Name: migrations_id_seq; Type: SEQUENCE; Schema: public; Owner: javier
--

CREATE SEQUENCE public.migrations_id_seq
    AS integer
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


ALTER SEQUENCE public.migrations_id_seq OWNER TO javier;

--
-- Name: migrations_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: javier
--

ALTER SEQUENCE public.migrations_id_seq OWNED BY public.migrations.id;


--
-- Name: password_reset_tokens; Type: TABLE; Schema: public; Owner: javier
--

CREATE TABLE public.password_reset_tokens (
    email character varying(255) NOT NULL,
    token character varying(255) NOT NULL,
    created_at timestamp(0) without time zone
);


ALTER TABLE public.password_reset_tokens OWNER TO javier;

--
-- Name: sessions; Type: TABLE; Schema: public; Owner: javier
--

CREATE TABLE public.sessions (
    id character varying(255) NOT NULL,
    user_id bigint,
    ip_address character varying(45),
    user_agent text,
    payload text NOT NULL,
    last_activity integer NOT NULL
);


ALTER TABLE public.sessions OWNER TO javier;

--
-- Name: transmisiones; Type: TABLE; Schema: public; Owner: javier
--

CREATE TABLE public.transmisiones (
    id bigint NOT NULL,
    evento_id bigint NOT NULL,
    stream_key character varying(255) NOT NULL,
    rtmp_server character varying(255),
    hls_url character varying(255),
    espectadores_activos integer DEFAULT 0 NOT NULL,
    activo boolean DEFAULT false NOT NULL,
    iniciado_en timestamp(0) without time zone,
    finalizado_en timestamp(0) without time zone,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone
);


ALTER TABLE public.transmisiones OWNER TO javier;

--
-- Name: transmisiones_id_seq; Type: SEQUENCE; Schema: public; Owner: javier
--

CREATE SEQUENCE public.transmisiones_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


ALTER SEQUENCE public.transmisiones_id_seq OWNER TO javier;

--
-- Name: transmisiones_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: javier
--

ALTER SEQUENCE public.transmisiones_id_seq OWNED BY public.transmisiones.id;


--
-- Name: users; Type: TABLE; Schema: public; Owner: javier
--

CREATE TABLE public.users (
    id bigint NOT NULL,
    name character varying(255) NOT NULL,
    email character varying(255) NOT NULL,
    email_verified_at timestamp(0) without time zone,
    password character varying(255) NOT NULL,
    remember_token character varying(100),
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone,
    role character varying(255) DEFAULT 'usuario'::character varying NOT NULL
);


ALTER TABLE public.users OWNER TO javier;

--
-- Name: users_id_seq; Type: SEQUENCE; Schema: public; Owner: javier
--

CREATE SEQUENCE public.users_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


ALTER SEQUENCE public.users_id_seq OWNER TO javier;

--
-- Name: users_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: javier
--

ALTER SEQUENCE public.users_id_seq OWNED BY public.users.id;


--
-- Name: carreras id; Type: DEFAULT; Schema: public; Owner: javier
--

ALTER TABLE ONLY public.carreras ALTER COLUMN id SET DEFAULT nextval('public.carreras_id_seq'::regclass);


--
-- Name: categorias id; Type: DEFAULT; Schema: public; Owner: javier
--

ALTER TABLE ONLY public.categorias ALTER COLUMN id SET DEFAULT nextval('public.categorias_id_seq'::regclass);


--
-- Name: departamentos id; Type: DEFAULT; Schema: public; Owner: javier
--

ALTER TABLE ONLY public.departamentos ALTER COLUMN id SET DEFAULT nextval('public.departamentos_id_seq'::regclass);


--
-- Name: eventos id; Type: DEFAULT; Schema: public; Owner: javier
--

ALTER TABLE ONLY public.eventos ALTER COLUMN id SET DEFAULT nextval('public.eventos_id_seq'::regclass);


--
-- Name: facultades id; Type: DEFAULT; Schema: public; Owner: javier
--

ALTER TABLE ONLY public.facultades ALTER COLUMN id SET DEFAULT nextval('public.facultades_id_seq'::regclass);


--
-- Name: failed_jobs id; Type: DEFAULT; Schema: public; Owner: javier
--

ALTER TABLE ONLY public.failed_jobs ALTER COLUMN id SET DEFAULT nextval('public.failed_jobs_id_seq'::regclass);


--
-- Name: jobs id; Type: DEFAULT; Schema: public; Owner: javier
--

ALTER TABLE ONLY public.jobs ALTER COLUMN id SET DEFAULT nextval('public.jobs_id_seq'::regclass);


--
-- Name: migrations id; Type: DEFAULT; Schema: public; Owner: javier
--

ALTER TABLE ONLY public.migrations ALTER COLUMN id SET DEFAULT nextval('public.migrations_id_seq'::regclass);


--
-- Name: transmisiones id; Type: DEFAULT; Schema: public; Owner: javier
--

ALTER TABLE ONLY public.transmisiones ALTER COLUMN id SET DEFAULT nextval('public.transmisiones_id_seq'::regclass);


--
-- Name: users id; Type: DEFAULT; Schema: public; Owner: javier
--

ALTER TABLE ONLY public.users ALTER COLUMN id SET DEFAULT nextval('public.users_id_seq'::regclass);


--
-- Data for Name: cache; Type: TABLE DATA; Schema: public; Owner: javier
--

COPY public.cache (key, value, expiration) FROM stdin;
\.


--
-- Data for Name: cache_locks; Type: TABLE DATA; Schema: public; Owner: javier
--

COPY public.cache_locks (key, owner, expiration) FROM stdin;
\.


--
-- Data for Name: carreras; Type: TABLE DATA; Schema: public; Owner: javier
--

COPY public.carreras (id, facultad_id, nombre, slug, created_at, updated_at) FROM stdin;
\.


--
-- Data for Name: categorias; Type: TABLE DATA; Schema: public; Owner: javier
--

COPY public.categorias (id, nombre, slug, descripcion, created_at, updated_at) FROM stdin;
1	General	general	\N	2026-07-22 15:45:20	2026-07-22 15:45:20
\.


--
-- Data for Name: departamentos; Type: TABLE DATA; Schema: public; Owner: javier
--

COPY public.departamentos (id, facultad_id, nombre, created_at, updated_at) FROM stdin;
\.


--
-- Data for Name: eventos; Type: TABLE DATA; Schema: public; Owner: javier
--

COPY public.eventos (id, titulo, slug, descripcion, tipo_evento, fecha_inicio, fecha_fin, lugar, organizador_id, facultad_id, carrera_id, categoria_id, imagen_principal, banner, miniatura, estado, url_transmision, duracion_minutos, responsable_streaming_id, created_at, updated_at, deleted_at) FROM stdin;
6	Conferencia Inaugural UEB 2026	conferencia-inaugural-ueb-2026	Transmisión oficial de bienvenida.	conferencia	2026-07-22 15:45:20	\N	\N	1	\N	\N	1	\N	\N	\N	en_vivo	\N	\N	\N	2026-07-22 15:45:20	2026-07-22 15:45:20	\N
\.


--
-- Data for Name: facultades; Type: TABLE DATA; Schema: public; Owner: javier
--

COPY public.facultades (id, nombre, slug, descripcion, created_at, updated_at) FROM stdin;
\.


--
-- Data for Name: failed_jobs; Type: TABLE DATA; Schema: public; Owner: javier
--

COPY public.failed_jobs (id, uuid, connection, queue, payload, exception, failed_at) FROM stdin;
\.


--
-- Data for Name: job_batches; Type: TABLE DATA; Schema: public; Owner: javier
--

COPY public.job_batches (id, name, total_jobs, pending_jobs, failed_jobs, failed_job_ids, options, cancelled_at, created_at, finished_at) FROM stdin;
\.


--
-- Data for Name: jobs; Type: TABLE DATA; Schema: public; Owner: javier
--

COPY public.jobs (id, queue, payload, attempts, reserved_at, available_at, created_at) FROM stdin;
\.


--
-- Data for Name: migrations; Type: TABLE DATA; Schema: public; Owner: javier
--

COPY public.migrations (id, migration, batch) FROM stdin;
1	0001_01_01_000000_create_users_table	1
2	0001_01_01_000001_create_cache_table	1
3	0001_01_01_000002_create_jobs_table	1
4	2026_07_22_144814_create_facultades_carreras_table	2
5	2026_07_22_144815_create_categorias_table	2
6	2026_07_22_144816_create_eventos_transmisiones_table	2
7	2026_07_22_150008_add_role_to_users_table	3
\.


--
-- Data for Name: password_reset_tokens; Type: TABLE DATA; Schema: public; Owner: javier
--

COPY public.password_reset_tokens (email, token, created_at) FROM stdin;
\.


--
-- Data for Name: sessions; Type: TABLE DATA; Schema: public; Owner: javier
--

COPY public.sessions (id, user_id, ip_address, user_agent, payload, last_activity) FROM stdin;
CLoXCTHa9y1IakNf9PkhD6YO3ECowucOu8sEETI8	1	192.168.100.7	Mozilla/5.0 (X11; Linux x86_64; rv:140.0) Gecko/20100101 Firefox/140.0	eyJfdG9rZW4iOiJna0lQUjRHY083c0xZcVpHbXQ3dllsWE1zaUF6SWJEaERHYWN2WlN3IiwiX2ZsYXNoIjp7Im9sZCI6W10sIm5ldyI6W119LCJfcHJldmlvdXMiOnsidXJsIjoiaHR0cDpcL1wvMTkyLjE2OC4xMDAuNzo4MDAwXC9ldmVudG9zXC82Iiwicm91dGUiOiJldmVudG9zLnNob3cifSwibG9naW5fd2ViXzU5YmEzNmFkZGMyYjJmOTQwMTU4MGYwMTRjN2Y1OGVhNGUzMDk4OWQiOjF9	1784838194
PAa1zmFHRRFOtVAGdmzLfY5OfkKkAJ1Sbicywuqj	1	10.219.229.14	Mozilla/5.0 (X11; Linux x86_64; rv:140.0) Gecko/20100101 Firefox/140.0	eyJfdG9rZW4iOiJleDRmblFQOGFueXEwMEdaa0FpQzNXNHFiR2lmSzZTYjVXNlk1Z05qIiwiX3ByZXZpb3VzIjp7InVybCI6Imh0dHA6XC9cLzEwLjIxOS4yMjkuMTQ6ODAwMFwvZXZlbnRvc1wvNiIsInJvdXRlIjoiZXZlbnRvcy5zaG93In0sIl9mbGFzaCI6eyJvbGQiOltdLCJuZXciOltdfSwibG9naW5fd2ViXzU5YmEzNmFkZGMyYjJmOTQwMTU4MGYwMTRjN2Y1OGVhNGUzMDk4OWQiOjF9	1785946372
\.


--
-- Data for Name: transmisiones; Type: TABLE DATA; Schema: public; Owner: javier
--

COPY public.transmisiones (id, evento_id, stream_key, rtmp_server, hls_url, espectadores_activos, activo, iniciado_en, finalizado_en, created_at, updated_at) FROM stdin;
6	6	ueb_SVAihAvb5tVi2WqHunDoZ592fT5Pb4In	rtmp://localhost/live	http://localhost/hls/ueb_SVAihAvb5tVi2WqHunDoZ592fT5Pb4In/index.m3u8	0	f	\N	\N	2026-07-22 15:45:20	2026-07-23 16:20:41
\.


--
-- Data for Name: users; Type: TABLE DATA; Schema: public; Owner: javier
--

COPY public.users (id, name, email, email_verified_at, password, remember_token, created_at, updated_at, role) FROM stdin;
2	Javier Solis Borja	javisolisborja@gmail.com	\N	$2y$12$jlDGS58k1VWveGH/HYHI6OxE7QFJ1JQvEYsKoWhdArKrVsTSvjYE6	\N	2026-07-22 15:54:40	2026-07-22 18:03:50	usuario
3	Test User	test@example.com	2026-07-23 15:31:23	$2y$12$zshJOs5HDB/fJdG8jE3eyeubqb.j39tfcyP/Mqt7VlNPLB4Fg5m6u	ALhk4lYPgp	2026-07-23 15:31:23	2026-07-23 15:31:23	usuario
1	Javier Administrador	admin@ueb.edu.ec	\N	$2y$12$ct.Ga6YDhSgALSQFFLbgMOgR3ue3qIGOXF9Xe.XnxPXeFu9hgL68a	\N	2026-07-22 15:28:56	2026-07-23 16:14:29	admin
4	Operador Streaming	operador@ueb.edu.ec	\N	$2y$12$mcFmzw5Q8xiT4ALkslFGDO/R6fEz6Yae6Som8Ra6TTpm3urHbGLZe	\N	2026-07-23 16:14:30	2026-07-23 16:14:30	operador
5	Estudiante Regular	usuario@ueb.edu.ec	\N	$2y$12$YBxIV0O7xkjqh8go6zEiheM1u25bEaaAjS/OMv52EOmDE/iZxs60e	\N	2026-07-23 16:14:30	2026-07-23 16:14:30	user
\.


--
-- Name: carreras_id_seq; Type: SEQUENCE SET; Schema: public; Owner: javier
--

SELECT pg_catalog.setval('public.carreras_id_seq', 1, false);


--
-- Name: categorias_id_seq; Type: SEQUENCE SET; Schema: public; Owner: javier
--

SELECT pg_catalog.setval('public.categorias_id_seq', 1, true);


--
-- Name: departamentos_id_seq; Type: SEQUENCE SET; Schema: public; Owner: javier
--

SELECT pg_catalog.setval('public.departamentos_id_seq', 1, false);


--
-- Name: eventos_id_seq; Type: SEQUENCE SET; Schema: public; Owner: javier
--

SELECT pg_catalog.setval('public.eventos_id_seq', 6, true);


--
-- Name: facultades_id_seq; Type: SEQUENCE SET; Schema: public; Owner: javier
--

SELECT pg_catalog.setval('public.facultades_id_seq', 1, false);


--
-- Name: failed_jobs_id_seq; Type: SEQUENCE SET; Schema: public; Owner: javier
--

SELECT pg_catalog.setval('public.failed_jobs_id_seq', 1, false);


--
-- Name: jobs_id_seq; Type: SEQUENCE SET; Schema: public; Owner: javier
--

SELECT pg_catalog.setval('public.jobs_id_seq', 1, false);


--
-- Name: migrations_id_seq; Type: SEQUENCE SET; Schema: public; Owner: javier
--

SELECT pg_catalog.setval('public.migrations_id_seq', 7, true);


--
-- Name: transmisiones_id_seq; Type: SEQUENCE SET; Schema: public; Owner: javier
--

SELECT pg_catalog.setval('public.transmisiones_id_seq', 6, true);


--
-- Name: users_id_seq; Type: SEQUENCE SET; Schema: public; Owner: javier
--

SELECT pg_catalog.setval('public.users_id_seq', 5, true);


--
-- Name: cache_locks cache_locks_pkey; Type: CONSTRAINT; Schema: public; Owner: javier
--

ALTER TABLE ONLY public.cache_locks
    ADD CONSTRAINT cache_locks_pkey PRIMARY KEY (key);


--
-- Name: cache cache_pkey; Type: CONSTRAINT; Schema: public; Owner: javier
--

ALTER TABLE ONLY public.cache
    ADD CONSTRAINT cache_pkey PRIMARY KEY (key);


--
-- Name: carreras carreras_pkey; Type: CONSTRAINT; Schema: public; Owner: javier
--

ALTER TABLE ONLY public.carreras
    ADD CONSTRAINT carreras_pkey PRIMARY KEY (id);


--
-- Name: categorias categorias_nombre_unique; Type: CONSTRAINT; Schema: public; Owner: javier
--

ALTER TABLE ONLY public.categorias
    ADD CONSTRAINT categorias_nombre_unique UNIQUE (nombre);


--
-- Name: categorias categorias_pkey; Type: CONSTRAINT; Schema: public; Owner: javier
--

ALTER TABLE ONLY public.categorias
    ADD CONSTRAINT categorias_pkey PRIMARY KEY (id);


--
-- Name: categorias categorias_slug_unique; Type: CONSTRAINT; Schema: public; Owner: javier
--

ALTER TABLE ONLY public.categorias
    ADD CONSTRAINT categorias_slug_unique UNIQUE (slug);


--
-- Name: departamentos departamentos_pkey; Type: CONSTRAINT; Schema: public; Owner: javier
--

ALTER TABLE ONLY public.departamentos
    ADD CONSTRAINT departamentos_pkey PRIMARY KEY (id);


--
-- Name: eventos eventos_pkey; Type: CONSTRAINT; Schema: public; Owner: javier
--

ALTER TABLE ONLY public.eventos
    ADD CONSTRAINT eventos_pkey PRIMARY KEY (id);


--
-- Name: eventos eventos_slug_unique; Type: CONSTRAINT; Schema: public; Owner: javier
--

ALTER TABLE ONLY public.eventos
    ADD CONSTRAINT eventos_slug_unique UNIQUE (slug);


--
-- Name: facultades facultades_nombre_unique; Type: CONSTRAINT; Schema: public; Owner: javier
--

ALTER TABLE ONLY public.facultades
    ADD CONSTRAINT facultades_nombre_unique UNIQUE (nombre);


--
-- Name: facultades facultades_pkey; Type: CONSTRAINT; Schema: public; Owner: javier
--

ALTER TABLE ONLY public.facultades
    ADD CONSTRAINT facultades_pkey PRIMARY KEY (id);


--
-- Name: facultades facultades_slug_unique; Type: CONSTRAINT; Schema: public; Owner: javier
--

ALTER TABLE ONLY public.facultades
    ADD CONSTRAINT facultades_slug_unique UNIQUE (slug);


--
-- Name: failed_jobs failed_jobs_pkey; Type: CONSTRAINT; Schema: public; Owner: javier
--

ALTER TABLE ONLY public.failed_jobs
    ADD CONSTRAINT failed_jobs_pkey PRIMARY KEY (id);


--
-- Name: failed_jobs failed_jobs_uuid_unique; Type: CONSTRAINT; Schema: public; Owner: javier
--

ALTER TABLE ONLY public.failed_jobs
    ADD CONSTRAINT failed_jobs_uuid_unique UNIQUE (uuid);


--
-- Name: job_batches job_batches_pkey; Type: CONSTRAINT; Schema: public; Owner: javier
--

ALTER TABLE ONLY public.job_batches
    ADD CONSTRAINT job_batches_pkey PRIMARY KEY (id);


--
-- Name: jobs jobs_pkey; Type: CONSTRAINT; Schema: public; Owner: javier
--

ALTER TABLE ONLY public.jobs
    ADD CONSTRAINT jobs_pkey PRIMARY KEY (id);


--
-- Name: migrations migrations_pkey; Type: CONSTRAINT; Schema: public; Owner: javier
--

ALTER TABLE ONLY public.migrations
    ADD CONSTRAINT migrations_pkey PRIMARY KEY (id);


--
-- Name: password_reset_tokens password_reset_tokens_pkey; Type: CONSTRAINT; Schema: public; Owner: javier
--

ALTER TABLE ONLY public.password_reset_tokens
    ADD CONSTRAINT password_reset_tokens_pkey PRIMARY KEY (email);


--
-- Name: sessions sessions_pkey; Type: CONSTRAINT; Schema: public; Owner: javier
--

ALTER TABLE ONLY public.sessions
    ADD CONSTRAINT sessions_pkey PRIMARY KEY (id);


--
-- Name: transmisiones transmisiones_pkey; Type: CONSTRAINT; Schema: public; Owner: javier
--

ALTER TABLE ONLY public.transmisiones
    ADD CONSTRAINT transmisiones_pkey PRIMARY KEY (id);


--
-- Name: transmisiones transmisiones_stream_key_unique; Type: CONSTRAINT; Schema: public; Owner: javier
--

ALTER TABLE ONLY public.transmisiones
    ADD CONSTRAINT transmisiones_stream_key_unique UNIQUE (stream_key);


--
-- Name: users users_email_unique; Type: CONSTRAINT; Schema: public; Owner: javier
--

ALTER TABLE ONLY public.users
    ADD CONSTRAINT users_email_unique UNIQUE (email);


--
-- Name: users users_pkey; Type: CONSTRAINT; Schema: public; Owner: javier
--

ALTER TABLE ONLY public.users
    ADD CONSTRAINT users_pkey PRIMARY KEY (id);


--
-- Name: cache_expiration_index; Type: INDEX; Schema: public; Owner: javier
--

CREATE INDEX cache_expiration_index ON public.cache USING btree (expiration);


--
-- Name: cache_locks_expiration_index; Type: INDEX; Schema: public; Owner: javier
--

CREATE INDEX cache_locks_expiration_index ON public.cache_locks USING btree (expiration);


--
-- Name: carreras_facultad_id_id_index; Type: INDEX; Schema: public; Owner: javier
--

CREATE INDEX carreras_facultad_id_id_index ON public.carreras USING btree (facultad_id, id);


--
-- Name: eventos_categoria_id_index; Type: INDEX; Schema: public; Owner: javier
--

CREATE INDEX eventos_categoria_id_index ON public.eventos USING btree (categoria_id);


--
-- Name: eventos_estado_fecha_inicio_index; Type: INDEX; Schema: public; Owner: javier
--

CREATE INDEX eventos_estado_fecha_inicio_index ON public.eventos USING btree (estado, fecha_inicio);


--
-- Name: failed_jobs_connection_queue_failed_at_index; Type: INDEX; Schema: public; Owner: javier
--

CREATE INDEX failed_jobs_connection_queue_failed_at_index ON public.failed_jobs USING btree (connection, queue, failed_at);


--
-- Name: jobs_queue_index; Type: INDEX; Schema: public; Owner: javier
--

CREATE INDEX jobs_queue_index ON public.jobs USING btree (queue);


--
-- Name: sessions_last_activity_index; Type: INDEX; Schema: public; Owner: javier
--

CREATE INDEX sessions_last_activity_index ON public.sessions USING btree (last_activity);


--
-- Name: sessions_user_id_index; Type: INDEX; Schema: public; Owner: javier
--

CREATE INDEX sessions_user_id_index ON public.sessions USING btree (user_id);


--
-- Name: transmisiones_evento_id_index; Type: INDEX; Schema: public; Owner: javier
--

CREATE INDEX transmisiones_evento_id_index ON public.transmisiones USING btree (evento_id);


--
-- Name: users_role_index; Type: INDEX; Schema: public; Owner: javier
--

CREATE INDEX users_role_index ON public.users USING btree (role);


--
-- Name: carreras carreras_facultad_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: javier
--

ALTER TABLE ONLY public.carreras
    ADD CONSTRAINT carreras_facultad_id_foreign FOREIGN KEY (facultad_id) REFERENCES public.facultades(id) ON DELETE CASCADE;


--
-- Name: departamentos departamentos_facultad_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: javier
--

ALTER TABLE ONLY public.departamentos
    ADD CONSTRAINT departamentos_facultad_id_foreign FOREIGN KEY (facultad_id) REFERENCES public.facultades(id) ON DELETE CASCADE;


--
-- Name: eventos eventos_carrera_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: javier
--

ALTER TABLE ONLY public.eventos
    ADD CONSTRAINT eventos_carrera_id_foreign FOREIGN KEY (carrera_id) REFERENCES public.carreras(id) ON DELETE SET NULL;


--
-- Name: eventos eventos_categoria_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: javier
--

ALTER TABLE ONLY public.eventos
    ADD CONSTRAINT eventos_categoria_id_foreign FOREIGN KEY (categoria_id) REFERENCES public.categorias(id) ON DELETE CASCADE;


--
-- Name: eventos eventos_facultad_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: javier
--

ALTER TABLE ONLY public.eventos
    ADD CONSTRAINT eventos_facultad_id_foreign FOREIGN KEY (facultad_id) REFERENCES public.facultades(id) ON DELETE SET NULL;


--
-- Name: eventos eventos_organizador_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: javier
--

ALTER TABLE ONLY public.eventos
    ADD CONSTRAINT eventos_organizador_id_foreign FOREIGN KEY (organizador_id) REFERENCES public.users(id) ON DELETE CASCADE;


--
-- Name: eventos eventos_responsable_streaming_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: javier
--

ALTER TABLE ONLY public.eventos
    ADD CONSTRAINT eventos_responsable_streaming_id_foreign FOREIGN KEY (responsable_streaming_id) REFERENCES public.users(id) ON DELETE SET NULL;


--
-- Name: transmisiones transmisiones_evento_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: javier
--

ALTER TABLE ONLY public.transmisiones
    ADD CONSTRAINT transmisiones_evento_id_foreign FOREIGN KEY (evento_id) REFERENCES public.eventos(id) ON DELETE CASCADE;


--
-- PostgreSQL database dump complete
--

\unrestrict 6toMfRVpSjNfoxNIu7ISI4RrXtffhN0CDtTkWJNUAW71sKXTDtLc8oe2cfbcgd3

