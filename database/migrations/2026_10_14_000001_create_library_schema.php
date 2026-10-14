<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // SQL explícito para conservar tipos, nombres, índices parciales y ausencia de DEFAULT.
        DB::unprepared(<<<'SQL'
            CREATE SCHEMA library;

            CREATE TABLE library.category (
                id uuid NOT NULL,
                name varchar(120) NOT NULL,
                CONSTRAINT pk_category PRIMARY KEY (id),
                CONSTRAINT uq_category_name UNIQUE (name)
            );

            CREATE TABLE library."user" (
                id uuid NOT NULL,
                username varchar(120) NOT NULL,
                password_hash varchar(512) NOT NULL,
                role varchar(40) NOT NULL,
                CONSTRAINT pk_user PRIMARY KEY (id),
                CONSTRAINT uq_user_username UNIQUE (username)
            );

            CREATE TABLE library.member (
                id uuid NOT NULL,
                full_name varchar(200) NOT NULL,
                document_number varchar(30) NOT NULL,
                CONSTRAINT pk_member PRIMARY KEY (id),
                CONSTRAINT uq_member_document_number UNIQUE (document_number)
            );

            CREATE TABLE library.book (
                id uuid NOT NULL,
                title varchar(250) NOT NULL,
                author varchar(200) NOT NULL,
                isbn char(13) NOT NULL,
                category_id uuid NOT NULL,
                total_copies integer NOT NULL,
                available_copies integer NOT NULL,
                cover_key varchar(512),
                deleted_at timestamptz,
                CONSTRAINT pk_book PRIMARY KEY (id),
                CONSTRAINT fk_book_category FOREIGN KEY (category_id)
                    REFERENCES library.category (id) ON DELETE RESTRICT ON UPDATE NO ACTION,
                CONSTRAINT ck_book_available_range CHECK (available_copies >= 0 AND available_copies <= total_copies)
            );

            CREATE UNIQUE INDEX uq_book_active_isbn ON library.book (isbn) WHERE deleted_at IS NULL;
            CREATE INDEX ix_book_active_category_title ON library.book (category_id, title) WHERE deleted_at IS NULL;

            CREATE TABLE library.loan (
                id uuid NOT NULL,
                member_id uuid NOT NULL,
                loaned_by_user_id uuid NOT NULL,
                loaned_at timestamptz NOT NULL,
                CONSTRAINT pk_loan PRIMARY KEY (id),
                CONSTRAINT fk_loan_member FOREIGN KEY (member_id)
                    REFERENCES library.member (id) ON DELETE RESTRICT ON UPDATE NO ACTION,
                CONSTRAINT fk_loan_user FOREIGN KEY (loaned_by_user_id)
                    REFERENCES library."user" (id) ON DELETE RESTRICT ON UPDATE NO ACTION
            );

            CREATE INDEX ix_loan_loaned_at ON library.loan (loaned_at);

            CREATE TABLE library.loan_item (
                id uuid NOT NULL,
                loan_id uuid NOT NULL,
                book_id uuid NOT NULL,
                book_title varchar(250) NOT NULL,
                isbn char(13) NOT NULL,
                category_name varchar(120) NOT NULL,
                due_at timestamptz NOT NULL,
                returned_at timestamptz,
                CONSTRAINT pk_loan_item PRIMARY KEY (id),
                CONSTRAINT uq_loan_item_loan_book UNIQUE (loan_id, book_id),
                CONSTRAINT fk_loan_item_loan FOREIGN KEY (loan_id)
                    REFERENCES library.loan (id) ON DELETE CASCADE ON UPDATE NO ACTION,
                CONSTRAINT fk_loan_item_book FOREIGN KEY (book_id)
                    REFERENCES library.book (id) ON DELETE RESTRICT ON UPDATE NO ACTION
            );

            CREATE INDEX ix_loan_item_book ON library.loan_item (book_id);
            CREATE INDEX ix_loan_item_open_due_at ON library.loan_item (due_at) WHERE returned_at IS NULL;
            SQL);

        DB::table('library.category')->insert([
            ['id' => '11111111-1111-4111-8111-111111111111', 'name' => 'Novela'],
            ['id' => '22222222-2222-4222-8222-222222222222', 'name' => 'Ciencia'],
            ['id' => '33333333-3333-4333-8333-333333333333', 'name' => 'Tecnología'],
            ['id' => '44444444-4444-4444-8444-444444444444', 'name' => 'Historia'],
            ['id' => '55555555-5555-4555-8555-555555555555', 'name' => 'Infantil'],
        ]);
    }

    public function down(): void
    {
        // No utilizar DROP SCHEMA CASCADE: un objeto ajeno debe impedir la reversa.
        DB::unprepared(<<<'SQL'
            DROP TABLE library.loan_item;
            DROP TABLE library.loan;
            DROP TABLE library.book;
            DROP TABLE library.member;
            DROP TABLE library."user";
            DROP TABLE library.category;
            DROP SCHEMA library;
            SQL);
    }
};
