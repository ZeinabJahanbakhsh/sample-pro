create table tags
(
    id         smallint identity
        constraint PK_tags
            primary key,
    name       nvarchar(50),
    created_at datetime not null,
    updated_at datetime not null
)
go

